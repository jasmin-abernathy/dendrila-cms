<?php
declare(strict_types=1);

namespace Dendrila\Cms\Storage;

use PDO;
use PDOException;
use RuntimeException;

final readonly class PdoCmsStore implements CmsStore
{
    public function __construct(private PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function transaction(callable $operation): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $operation();
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function actorHasPermission(string $siteId, string $actorId, string $permission): bool
    {
        $statement = $this->pdo->prepare(
            'SELECT 1 FROM memberships m
             INNER JOIN role_permissions p ON p.site_id = m.site_id AND p.role_id = m.role_id
             WHERE m.site_id = :site_id AND m.actor_id = :actor_id AND p.permission = :permission
             LIMIT 1 FOR UPDATE'
        );
        $statement->execute(['site_id' => $siteId, 'actor_id' => $actorId, 'permission' => $permission]);
        return $statement->fetchColumn() !== false;
    }

    public function findContentType(string $siteId, string $typeId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT definition_json FROM content_types WHERE site_id = :site_id AND type_id = :type_id AND enabled = 1'
        );
        $statement->execute(['site_id' => $siteId, 'type_id' => $typeId]);
        $json = $statement->fetchColumn();
        if (!is_string($json)) {
            return null;
        }
        $definition = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return is_array($definition) ? $definition : null;
    }

    public function findSitePolicy(string $siteId): array
    {
        $statement = $this->pdo->prepare('SELECT policy_json FROM sites WHERE site_id = :site_id FOR UPDATE');
        $statement->execute(['site_id' => $siteId]);
        $json = $statement->fetchColumn();
        if (!is_string($json)) {
            throw new RuntimeException('Site does not exist.');
        }
        $policy = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return is_array($policy) ? $policy : [];
    }

    public function incrementDataRevision(string $siteId): int
    {
        $statement = $this->pdo->prepare(
            'UPDATE sites SET data_revision = data_revision + 1, updated_at = UTC_TIMESTAMP(6) WHERE site_id = :site_id'
        );
        $statement->execute(['site_id' => $siteId]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Site does not exist.');
        }
        $read = $this->pdo->prepare('SELECT data_revision FROM sites WHERE site_id = :site_id');
        $read->execute(['site_id' => $siteId]);
        return (int) $read->fetchColumn();
    }

    public function findContentForUpdate(string $siteId, string $contentId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT site_id, content_id, created_by_id, type_id, slug, status, values_json, published_values_json,
                    published_slug, published_version, published_revision_id,
                    version, current_revision_id, publish_at, review_approved_at, published_at, created_at, updated_at
             FROM contents WHERE site_id = :site_id AND content_id = :content_id FOR UPDATE'
        );
        $statement->execute(['site_id' => $siteId, 'content_id' => $contentId]);
        $row = $statement->fetch();
        return $row === false ? null : $this->decodeContent($row);
    }

    public function findContent(string $siteId, string $contentId, bool $includeDrafts = true, ?string $ownDraftActorId = null): ?array
    {
        $visibility = $includeDrafts ? '' : ' AND (published_values_json IS NOT NULL AND status <> \'archived\'';
        if (!$includeDrafts && $ownDraftActorId !== null) {
            $visibility .= ' OR (created_by_id = :own_actor_id AND status IN (\'draft\', \'review\'))';
        }
        if (!$includeDrafts) {
            $visibility .= ')';
        }
        $statement = $this->pdo->prepare(
            'SELECT site_id, content_id, created_by_id, type_id, slug, status, values_json, published_values_json,
                    published_slug, published_version, published_revision_id,
                    version, current_revision_id, publish_at, review_approved_at, published_at, created_at, updated_at
             FROM contents WHERE site_id = :site_id AND content_id = :content_id' . $visibility
        );
        $parameters = ['site_id' => $siteId, 'content_id' => $contentId];
        if (!$includeDrafts && $ownDraftActorId !== null) {
            $parameters['own_actor_id'] = $ownDraftActorId;
        }
        $statement->execute($parameters);
        $row = $statement->fetch();
        return $row === false ? null : $this->decodeContent($row);
    }

    public function listContents(string $siteId, array $filters, bool $includeDrafts = true, ?string $ownDraftActorId = null): array
    {
        $where = ['site_id = :site_id'];
        $parameters = ['site_id' => $siteId];
        if (!$includeDrafts) {
            if (isset($filters['status']) && $filters['status'] !== 'published') {
                if ($ownDraftActorId === null || !in_array($filters['status'], ['draft', 'review'], true)) {
                    return [];
                }
                $where[] = 'created_by_id = :own_status_actor AND status = :own_status';
                $parameters['own_status_actor'] = $ownDraftActorId;
                $parameters['own_status'] = $filters['status'];
            } else {
                $public = '(published_values_json IS NOT NULL AND status <> \'archived\')';
                if ($ownDraftActorId !== null) {
                    $where[] = '(' . $public . ' OR (created_by_id = :own_actor_id AND status IN (\'draft\', \'review\')))';
                    $parameters['own_actor_id'] = $ownDraftActorId;
                } else {
                    $where[] = $public;
                }
            }
        }
        if (isset($filters['type'])) {
            $where[] = 'type_id = :type_id';
            $parameters['type_id'] = $filters['type'];
        }
        if ($includeDrafts && isset($filters['status'])) {
            if ($filters['status'] === 'published') {
                $where[] = 'published_values_json IS NOT NULL AND status <> \'archived\'';
            } else {
                $where[] = 'status = :status';
                $parameters['status'] = $filters['status'];
            }
        }
        if (isset($filters['search']) && $filters['search'] !== '') {
            $where[] = '(slug LIKE :search OR values_json LIKE :search_values OR published_slug LIKE :published_slug OR published_values_json LIKE :published_values)';
            $escaped = strtr($filters['search'], ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
            $parameters['search'] = '%' . $escaped . '%';
            $parameters['search_values'] = '%' . $escaped . '%';
            $parameters['published_slug'] = '%' . $escaped . '%';
            $parameters['published_values'] = '%' . $escaped . '%';
        }
        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, min(100000, (int) ($filters['offset'] ?? 0)));
        $sql = 'SELECT site_id, content_id, created_by_id, type_id, slug, status, values_json, published_values_json,
                       published_slug, published_version, published_revision_id,
                       version, current_revision_id, publish_at, review_approved_at, published_at, created_at, updated_at
                FROM contents WHERE ' . implode(' AND ', $where)
            . ' ORDER BY updated_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return array_map(fn (array $row): array => $this->decodeContent($row), $statement->fetchAll());
    }

    public function insertContent(array $content): void
    {
        \Dendrila\Cms\Media\MediaReferences::guardContent($this->pdo, $content,
            $this->findContentType($content['site_id'], $content['type_id']) ?? []);
        $statement = $this->pdo->prepare(
            'INSERT INTO contents
              (site_id, content_id, created_by_id, type_id, slug, status, values_json, published_values_json,
               published_slug, published_version, published_revision_id,
               version, current_revision_id, publish_at, review_approved_at, published_at, created_at, updated_at)
             VALUES
               (:site_id, :content_id, :created_by_id, :type_id, :slug, :status, :values_json, :published_values_json,
               :published_slug, :published_version, :published_revision_id,
               :version, :current_revision_id, :publish_at, :review_approved_at, :published_at, :created_at, :updated_at)'
        );
        $statement->execute($this->contentParameters($content));
    }

    public function saveContent(array $content, int $expectedVersion): void
    {
        \Dendrila\Cms\Media\MediaReferences::guardContent($this->pdo, $content,
            $this->findContentType($content['site_id'], $content['type_id']) ?? []);
        $parameters = $this->contentParameters($content);
        unset($parameters['created_at']);
        unset($parameters['created_by_id']);
        $parameters['expected_version'] = $expectedVersion;
        $statement = $this->pdo->prepare(
            'UPDATE contents SET type_id = :type_id, slug = :slug, status = :status,
                values_json = :values_json, published_values_json = :published_values_json,
                published_slug = :published_slug, published_version = :published_version,
                published_revision_id = :published_revision_id,
                version = :version, current_revision_id = :current_revision_id,
                publish_at = :publish_at, review_approved_at = :review_approved_at,
                published_at = :published_at, updated_at = :updated_at
             WHERE site_id = :site_id AND content_id = :content_id AND version = :expected_version'
        );
        $statement->execute($parameters);
        if ($statement->rowCount() !== 1) {
            throw new \Dendrila\Cms\Content\VersionConflict('The content changed during the update.');
        }
    }

    public function appendRevision(array $revision): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO revisions
              (site_id, revision_id, content_id, version, author_id, source, change_summary, snapshot_json, created_at)
             VALUES (:site_id, :revision_id, :content_id, :version, :author_id, :source, :change_summary, :snapshot_json, :created_at)'
        );
        $statement->execute([
            'site_id' => $revision['site_id'],
            'revision_id' => $revision['revision_id'],
            'content_id' => $revision['content_id'],
            'version' => $revision['version'],
            'author_id' => $revision['author_id'],
            'source' => $revision['source'],
            'change_summary' => $revision['change_summary'],
            'snapshot_json' => self::encode($revision['snapshot']),
            'created_at' => $revision['created_at'],
        ]);
    }

    public function findRevision(string $siteId, string $contentId, string $revisionId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT revision_id, site_id, content_id, version, author_id, source, change_summary, snapshot_json, created_at
             FROM revisions WHERE site_id = :site_id AND content_id = :content_id AND revision_id = :revision_id'
        );
        $statement->execute(['site_id' => $siteId, 'content_id' => $contentId, 'revision_id' => $revisionId]);
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }
        $row['version'] = (int) $row['version'];
        $row['snapshot'] = json_decode($row['snapshot_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['snapshot_json']);
        return $row;
    }

    public function findIdempotency(string $siteId, string $actorId, string $requestId): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT request_hash, response_json FROM idempotency_requests
             WHERE site_id = :site_id AND actor_id = :actor_id AND client_request_id = :request_id FOR UPDATE'
        );
        $statement->execute(['site_id' => $siteId, 'actor_id' => $actorId, 'request_id' => $requestId]);
        $row = $statement->fetch();
        return $row === false ? null : [
            'request_hash' => $row['request_hash'],
            'response' => $row['response_json'] === null
                ? null : json_decode($row['response_json'], true, 512, JSON_THROW_ON_ERROR),
        ];
    }

    public function claimIdempotency(string $siteId, string $actorId, string $requestId, string $requestHash): bool
    {
        $statement = $this->pdo->prepare(
            'INSERT IGNORE INTO idempotency_requests
              (site_id, actor_id, client_request_id, request_hash, response_json, created_at)
             VALUES (:site_id, :actor_id, :request_id, :request_hash, NULL, UTC_TIMESTAMP(6))'
        );
        $statement->execute([
            'site_id' => $siteId,
            'actor_id' => $actorId,
            'request_id' => $requestId,
            'request_hash' => $requestHash,
        ]);
        return $statement->rowCount() === 1;
    }

    public function completeIdempotency(string $siteId, string $actorId, string $requestId, string $responseJson): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE idempotency_requests SET response_json = :response_json
             WHERE site_id = :site_id AND actor_id = :actor_id AND client_request_id = :request_id AND response_json IS NULL'
        );
        $statement->execute([
            'response_json' => $responseJson,
            'site_id' => $siteId,
            'actor_id' => $actorId,
            'request_id' => $requestId,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new RuntimeException('Could not finalize the idempotent response.');
        }
    }

    public function appendAudit(
        string $siteId,
        ?string $actorId,
        ?string $deviceId,
        string $action,
        string $entityType,
        ?string $entityId,
        string $outcome,
        array $details,
    ): void {
        $statement = $this->pdo->prepare(
            'INSERT INTO audit_events
              (site_id, actor_id, device_id, action, entity_type, entity_id, outcome, details_json, created_at)
             VALUES (:site_id, :actor_id, :device_id, :action, :entity_type, :entity_id, :outcome, :details_json, UTC_TIMESTAMP(6))'
        );
        $statement->execute([
            'site_id' => $siteId,
            'actor_id' => $actorId,
            'device_id' => $deviceId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'outcome' => $outcome,
            'details_json' => self::encode($details),
        ]);
    }

    public function findDuePublications(string $siteId, string $nowUtc, int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $statement = $this->pdo->prepare(
            'SELECT site_id, content_id FROM contents
             WHERE site_id = :site_id AND status = \'scheduled\' AND publish_at <= :now_utc
             ORDER BY publish_at ASC LIMIT ' . $limit
        );
        $statement->execute(['site_id' => $siteId, 'now_utc' => $nowUtc]);
        return $statement->fetchAll();
    }

    /** @return array<string, mixed> */
    private function contentParameters(array $content): array
    {
        return [
            'site_id' => $content['site_id'],
            'content_id' => $content['content_id'],
            'created_by_id' => $content['created_by_id'],
            'type_id' => $content['type_id'],
            'slug' => $content['slug'],
            'status' => $content['status'],
            'values_json' => self::encode($content['values']),
            'published_values_json' => $content['published_values'] === null
                ? null : self::encode($content['published_values']),
            'published_slug' => $content['published_slug'],
            'published_version' => $content['published_version'],
            'published_revision_id' => $content['published_revision_id'],
            'version' => $content['version'],
            'current_revision_id' => $content['current_revision_id'],
            'publish_at' => $content['publish_at'],
            'review_approved_at' => $content['review_approved_at'],
            'published_at' => $content['published_at'],
            'created_at' => $content['created_at'],
            'updated_at' => $content['updated_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function decodeContent(array $row): array
    {
        $row['values'] = json_decode($row['values_json'], true, 512, JSON_THROW_ON_ERROR);
        $row['published_values'] = $row['published_values_json'] === null
            ? null : json_decode($row['published_values_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($row['values_json'], $row['published_values_json']);
        $row['version'] = (int) $row['version'];
        $row['published_version'] = $row['published_version'] === null ? null : (int) $row['published_version'];
        return $row;
    }

    private static function encode(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

