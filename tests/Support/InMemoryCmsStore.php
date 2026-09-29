<?php
declare(strict_types=1);

namespace LePotager\Cms\Tests\Support;

use LePotager\Cms\Content\VersionConflict;
use LePotager\Cms\Storage\CmsStore;

final class InMemoryCmsStore implements CmsStore
{
    public array $permissions = [];
    public array $types = [];
    public array $policies = [];
    public array $contents = [];
    public array $revisions = [];
    public array $idempotency = [];
    public array $audit = [];
    public array $siteRevisions = [];

    public function transaction(callable $operation): mixed
    {
        $snapshot = serialize([
            $this->contents, $this->revisions, $this->idempotency, $this->audit, $this->siteRevisions,
        ]);
        try {
            return $operation();
        } catch (\Throwable $error) {
            [
                $this->contents, $this->revisions, $this->idempotency, $this->audit, $this->siteRevisions,
            ] = unserialize($snapshot, ['allowed_classes' => false]);
            throw $error;
        }
    }

    public function actorHasPermission(string $siteId, string $actorId, string $permission): bool
    {
        return in_array($permission, $this->permissions[$siteId][$actorId] ?? [], true);
    }

    public function findContentType(string $siteId, string $typeId): ?array
    {
        return $this->types[$siteId][$typeId] ?? null;
    }

    public function findSitePolicy(string $siteId): array
    {
        return $this->policies[$siteId] ?? [];
    }

    public function incrementDataRevision(string $siteId): int
    {
        return $this->siteRevisions[$siteId] = ($this->siteRevisions[$siteId] ?? 1) + 1;
    }

    public function findContentForUpdate(string $siteId, string $contentId): ?array
    {
        return $this->contents[$siteId][$contentId] ?? null;
    }

    public function findContent(string $siteId, string $contentId, bool $includeDrafts = true, ?string $ownDraftActorId = null): ?array
    {
        $content = $this->contents[$siteId][$contentId] ?? null;
        $isOwnDraft = $content !== null && $ownDraftActorId !== null
            && $content['created_by_id'] === $ownDraftActorId
            && in_array($content['status'], ['draft', 'review'], true);
        $isPublic = $content !== null && $content['published_values'] !== null && $content['status'] !== 'archived';
        if (!$includeDrafts && !$isOwnDraft && !$isPublic) {
            return null;
        }
        return $content;
    }

    public function listContents(string $siteId, array $filters, bool $includeDrafts = true, ?string $ownDraftActorId = null): array
    {
        $contents = array_values($this->contents[$siteId] ?? []);
        if (!$includeDrafts) {
            if (isset($filters['status']) && $filters['status'] !== 'published') {
                if ($ownDraftActorId === null || !in_array($filters['status'], ['draft', 'review'], true)) {
                    return [];
                }
                $contents = array_filter($contents, static fn (array $item): bool =>
                    $item['created_by_id'] === $ownDraftActorId && $item['status'] === $filters['status']);
            } else {
                $contents = array_filter($contents, static fn (array $item): bool =>
                    ($item['published_values'] !== null && $item['status'] !== 'archived')
                    || ($ownDraftActorId !== null && $item['created_by_id'] === $ownDraftActorId
                        && in_array($item['status'], ['draft', 'review'], true)));
            }
        }
        if (isset($filters['type'])) {
            $contents = array_filter($contents, static fn (array $item): bool => $item['type_id'] === $filters['type']);
        }
        if ($includeDrafts && isset($filters['status'])) {
            $contents = $filters['status'] === 'published'
                ? array_filter($contents, static fn (array $item): bool =>
                    $item['published_values'] !== null && $item['status'] !== 'archived')
                : array_filter($contents, static fn (array $item): bool => $item['status'] === $filters['status']);
        }
        if (isset($filters['search'])) {
            $needle = (string) $filters['search'];
            $contents = array_filter($contents, static fn (array $item): bool =>
                str_contains($item['slug'], $needle) || str_contains(json_encode($item['values']), $needle));
        }
        return array_slice(array_values($contents), (int) ($filters['offset'] ?? 0), (int) ($filters['limit'] ?? 50));
    }

    public function insertContent(array $content): void
    {
        $this->contents[$content['site_id']][$content['content_id']] = $content;
    }

    public function saveContent(array $content, int $expectedVersion): void
    {
        $current = $this->contents[$content['site_id']][$content['content_id']] ?? null;
        if ($current === null || $current['version'] !== $expectedVersion) {
            throw new VersionConflict('Content version is stale.');
        }
        $this->contents[$content['site_id']][$content['content_id']] = $content;
    }

    public function appendRevision(array $revision): void
    {
        $this->revisions[$revision['site_id']][$revision['revision_id']] = $revision;
    }

    public function findRevision(string $siteId, string $contentId, string $revisionId): ?array
    {
        $revision = $this->revisions[$siteId][$revisionId] ?? null;
        return $revision !== null && $revision['content_id'] === $contentId ? $revision : null;
    }

    public function findIdempotency(string $siteId, string $actorId, string $requestId): ?array
    {
        $entry = $this->idempotency[$siteId][$actorId][$requestId] ?? null;
        return $entry === null ? null : [
            'request_hash' => $entry['request_hash'],
            'response' => $entry['response'],
        ];
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
        $this->audit[] = compact('siteId', 'actorId', 'deviceId', 'action', 'entityType', 'entityId', 'outcome', 'details');
    }

    public function findDuePublications(string $siteId, string $nowUtc, int $limit): array
    {
        $due = [];
        foreach ($this->contents[$siteId] ?? [] as $content) {
            if ($content['status'] === 'scheduled' && $content['publish_at'] <= $nowUtc) {
                $due[] = ['site_id' => $siteId, 'content_id' => $content['content_id']];
            }
        }
        return array_slice($due, 0, $limit);
    }

    public function claimIdempotency(string $siteId, string $actorId, string $requestId, string $requestHash): bool
    {
        if (isset($this->idempotency[$siteId][$actorId][$requestId])) {
            return false;
        }
        $this->idempotency[$siteId][$actorId][$requestId] = ['request_hash' => $requestHash, 'response' => null];
        return true;
    }

    public function completeIdempotency(string $siteId, string $actorId, string $requestId, string $responseJson): void
    {
        $this->idempotency[$siteId][$actorId][$requestId]['response'] = json_decode($responseJson, true, 512, JSON_THROW_ON_ERROR);
    }
}
