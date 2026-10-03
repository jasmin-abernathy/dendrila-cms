<?php
declare(strict_types=1);

namespace Dendrila\Cms\Content;

use DateTimeImmutable;
use DateTimeZone;
use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Storage\CmsStore;

final readonly class ContentService
{
    public function __construct(
        private CmsStore $store,
        private AuthorizationService $authorization,
    ) {
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    public function createDraft(
        ActorContext $actor,
        string $typeId,
        array $values,
        string $requestId,
        bool $submitForReview = false,
    ): array
    {
        return $this->mutate($actor, $requestId, [
            'op' => 'create', 'type' => $typeId, 'values' => $values, 'submit_for_review' => $submitForReview,
        ], function () use ($actor, $typeId, $values, $submitForReview): array {
            $this->authorization->require($actor, 'content.create');
            if ($submitForReview) {
                $this->authorization->require($actor, 'content.submit_for_review');
            }
            $rawDefinition = $this->store->findContentType($actor->siteId, $typeId);
            if ($rawDefinition === null) {
                throw new ContentNotFound('Content type is not enabled for this site.');
            }
            $definition = new ContentTypeDefinition($rawDefinition);
            $definition->validateValues($values);

            $now = self::now();
            $contentId = bin2hex(random_bytes(16));
            $revisionId = bin2hex(random_bytes(16));
            $content = [
                'site_id' => $actor->siteId,
                'content_id' => $contentId,
                'created_by_id' => $actor->actorId,
                'type_id' => $typeId,
                'slug' => $this->slugFrom($values, $contentId),
                'status' => $submitForReview ? ContentStatus::Review->value : ContentStatus::Draft->value,
                'values' => $values,
                'published_values' => null,
                'published_slug' => null,
                'published_version' => null,
                'published_revision_id' => null,
                'version' => 1,
                'current_revision_id' => $revisionId,
                'publish_at' => null,
                'review_approved_at' => null,
                'published_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->store->insertContent($content);
            $this->store->appendRevision($this->revision($content, $actor, $revisionId,
                $submitForReview ? 'Création envoyée en revue' : 'Création du brouillon', $actor->source));
            $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId,
                'content.create', 'content', $contentId, 'allowed', ['type_id' => $typeId]);
            $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            return $this->response($content, $submitForReview ? 'pending_review' : 'applied', $siteRevision);
        });
    }

    /** @return array<string, mixed> */
    public function get(ActorContext $actor, string $contentId): array
    {
        $this->authorization->require($actor, 'content.read');
        $includeDrafts = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.read_draft');
        $ownDrafts = !$includeDrafts && $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.read_own_draft');
        $content = $this->store->findContent($actor->siteId, $contentId, $includeDrafts, $ownDrafts ? $actor->actorId : null)
            ?? throw new ContentNotFound('Content does not exist in this site.');
        return $includeDrafts || $this->isOwnWorkingCopy($content, $actor) ? $content : $this->publishedProjection($content);
    }

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function list(ActorContext $actor, array $filters = []): array
    {
        $this->authorization->require($actor, 'content.read');
        foreach (['type', 'status', 'search', 'limit', 'offset'] as $key) {
            if (isset($filters[$key]) && !is_scalar($filters[$key])) {
                throw new \InvalidArgumentException("Filter '{$key}' must be scalar.");
            }
        }
        if (isset($filters['status']) && ContentStatus::tryFrom((string) $filters['status']) === null) {
            throw new \InvalidArgumentException('Unknown content status filter.');
        }
        if (isset($filters['search']) && strlen((string) $filters['search']) > 120) {
            throw new \InvalidArgumentException('Search query is too long.');
        }
        $filters['limit'] = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $filters['offset'] = max(0, min(100000, (int) ($filters['offset'] ?? 0)));
        $includeDrafts = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.read_draft');
        $ownDrafts = !$includeDrafts && $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.read_own_draft');
        $items = $this->store->listContents($actor->siteId, $filters, $includeDrafts, $ownDrafts ? $actor->actorId : null);
        if ($includeDrafts) {
            return $items;
        }
        return array_map(fn (array $item): array => $this->isOwnWorkingCopy($item, $actor)
            ? $item : $this->publishedProjection($item), $items);
    }

    /** @param array<string, mixed> $values @return array<string, mixed> */
    public function updateDraft(
        ActorContext $actor,
        string $contentId,
        int $expectedVersion,
        array $values,
        string $requestId,
    ): array {
        return $this->mutate($actor, $requestId, [
            'op' => 'update', 'content_id' => $contentId, 'expected_version' => $expectedVersion, 'values' => $values,
        ], function () use ($actor, $contentId, $expectedVersion, $values): array {
            $canUpdateAny = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.update');
            if (!$canUpdateAny) {
                $this->authorization->require($actor, 'content.update_own');
            }
            $content = $this->load($actor, $contentId);
            if (!$canUpdateAny && $content['created_by_id'] !== $actor->actorId) {
                throw new ContentNotFound('Content does not exist in this site.');
            }
            $this->assertVersion($content, $expectedVersion);
            if (!in_array($content['status'], ['draft', 'review', 'published'], true)) {
                throw new InvalidTransition('Only working or published content can be edited.');
            }
            $rawDefinition = $this->store->findContentType($actor->siteId, $content['type_id']);
            if ($rawDefinition === null) {
                throw new ContentNotFound('Content type is no longer enabled.');
            }
            (new ContentTypeDefinition($rawDefinition))->validateValues($values);

            $oldVersion = $content['version'];
            $content['values'] = $values;
            $content['slug'] = $this->slugFrom($values, $content['content_id']);
            if ($content['status'] === 'published') {
                $content['status'] = 'draft';
            }
            $content['version'] = $oldVersion + 1;
            $content['publish_at'] = null;
            $content['review_approved_at'] = null;
            $content['updated_at'] = self::now();
            $revisionId = bin2hex(random_bytes(16));
            $content['current_revision_id'] = $revisionId;
            $this->store->saveContent($content, $oldVersion);
            $this->store->appendRevision($this->revision($content, $actor, $revisionId, 'Mise à jour du brouillon', $actor->source));
            $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId,
                'content.update', 'content', $contentId, 'allowed', ['version' => $content['version']]);
            $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            return $this->response($content, 'applied', $siteRevision);
        });
    }

    /** @param array<string, mixed> $fields @return array<string, mixed> */
    public function updateFields(
        ActorContext $actor,
        string $contentId,
        array $fields,
        string $requestId,
        ?string $expectedTypeId = null,
        bool $submitForReview = false,
    ): array
    {
        if ($fields === []) {
            throw new \InvalidArgumentException('At least one field must be provided.');
        }
        return $this->mutate($actor, $requestId, [
            'op' => 'patch', 'content_id' => $contentId, 'type_id' => $expectedTypeId,
            'fields' => $fields, 'submit_for_review' => $submitForReview,
        ], function () use ($actor, $contentId, $fields, $expectedTypeId, $submitForReview): array {
            $canUpdateAny = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.update');
            if (!$canUpdateAny) {
                $this->authorization->require($actor, 'content.update_own');
            }
            $content = $this->load($actor, $contentId);
            if ($expectedTypeId !== null && $content['type_id'] !== $expectedTypeId) {
                throw new ContentNotFound('Content does not belong to this module.');
            }
            if (!$canUpdateAny && $content['created_by_id'] !== $actor->actorId) {
                throw new ContentNotFound('Content does not exist in this site.');
            }
            if (!in_array($content['status'], ['draft', 'review', 'published'], true)) {
                throw new InvalidTransition('Only working or published content can be edited.');
            }
            $rawDefinition = $this->store->findContentType($actor->siteId, $content['type_id']);
            if ($rawDefinition === null) {
                throw new ContentNotFound('Content type is no longer enabled.');
            }
            $values = array_replace($content['values'], $fields);
            (new ContentTypeDefinition($rawDefinition))->validateValues($values);
            if ($submitForReview) {
                $this->authorization->require($actor, 'content.submit_for_review');
            }

            $oldVersion = $content['version'];
            $content['values'] = $values;
            $content['slug'] = $this->slugFrom($values, $content['content_id']);
            if ($content['status'] === 'published') {
                $content['status'] = 'draft';
            }
            if ($submitForReview) {
                $content['status'] = ContentStatus::Review->value;
            }
            $content['version'] = $oldVersion + 1;
            $content['publish_at'] = null;
            $content['review_approved_at'] = null;
            $content['updated_at'] = self::now();
            $revisionId = bin2hex(random_bytes(16));
            $content['current_revision_id'] = $revisionId;
            $this->store->saveContent($content, $oldVersion);
            $this->store->appendRevision($this->revision($content, $actor, $revisionId,
                'Mise à jour des champs depuis MMW', $actor->source));
            $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId,
                'content.update', 'content', $contentId, 'allowed', ['version' => $content['version']]);
            $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            return $this->response($content, $submitForReview ? 'pending_review' : 'applied', $siteRevision);
        });
    }

    /** @return array<string, mixed> */
    public function archive(ActorContext $actor, string $contentId, string $requestId, ?string $expectedTypeId = null): array
    {
        return $this->mutate($actor, $requestId, [
            'op' => 'archive', 'content_id' => $contentId, 'type_id' => $expectedTypeId,
        ], function () use ($actor, $contentId, $expectedTypeId): array {
            $content = $this->load($actor, $contentId);
            if ($expectedTypeId !== null && $content['type_id'] !== $expectedTypeId) {
                throw new ContentNotFound('Content does not belong to this module.');
            }
            $current = ContentStatus::from($content['status']);
            $this->authorization->require($actor, 'content.archive');
            if (!$this->transitionAllowed($current, ContentStatus::Archived)) {
                throw new InvalidTransition("Content in {$current->value} state cannot be archived.");
            }
            $oldVersion = $content['version'];
            $content['status'] = ContentStatus::Archived->value;
            $content['publish_at'] = null;
            $content['version'] = $oldVersion + 1;
            $content['updated_at'] = self::now();
            $revisionId = bin2hex(random_bytes(16));
            $content['current_revision_id'] = $revisionId;
            $this->store->saveContent($content, $oldVersion);
            $this->store->appendRevision($this->revision($content, $actor, $revisionId,
                'Archivage demandé depuis MMW', $actor->source));
            $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId,
                'content.archived', 'content', $contentId, 'allowed', ['version' => $content['version']]);
            $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            return $this->response($content, 'applied', $siteRevision);
        });
    }

    /** @return array<string, mixed> */
    public function transition(
        ActorContext $actor,
        string $contentId,
        string $targetStatus,
        int $expectedVersion,
        string $requestId,
        ?string $publishAt = null,
    ): array {
        return $this->mutate($actor, $requestId, [
            'op' => 'transition', 'content_id' => $contentId, 'target' => $targetStatus,
            'expected_version' => $expectedVersion, 'publish_at' => $publishAt,
        ], function () use ($actor, $contentId, $targetStatus, $expectedVersion, $publishAt): array {
            $content = $this->load($actor, $contentId);
            $this->assertVersion($content, $expectedVersion);
            $current = ContentStatus::from($content['status']);
            $target = ContentStatus::tryFrom($targetStatus)
                ?? throw new InvalidTransition('Unknown target status.');
            $canUpdateAny = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.update');
            if ($target === ContentStatus::Draft && !$canUpdateAny) {
                $this->authorization->require($actor, 'content.update_own');
                if ($content['created_by_id'] !== $actor->actorId) {
                    throw new ContentNotFound('Content does not exist in this site.');
                }
            } else {
                $this->authorization->require($actor, $this->permissionFor($current, $target));
                if ($target === ContentStatus::Review && !$canUpdateAny && $content['created_by_id'] !== $actor->actorId) {
                    throw new ContentNotFound('Content does not exist in this site.');
                }
            }
            if ($target === ContentStatus::Scheduled) {
                $this->authorization->require($actor, 'content.publish');
            }
            if (!$this->transitionAllowed($current, $target)) {
                throw new InvalidTransition("Transition {$current->value} -> {$target->value} is not allowed.");
            }

            $policy = $this->store->findSitePolicy($actor->siteId);
            $resultStatus = 'applied';
            $actualTarget = $target;
            if ($target === ContentStatus::Published && $current === ContentStatus::Draft
                && ($policy['review_before_publish'] ?? false) === true
                && !$this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.publish_without_review')) {
                $actualTarget = ContentStatus::Review;
                $resultStatus = 'pending_review';
            }
            if ($target === ContentStatus::Scheduled) {
                if ($publishAt === null) {
                    throw new InvalidTransition('A scheduled publication needs publish_at.');
                }
                $normalizedPublishAt = $this->futureUtc($publishAt);
                if ($current === ContentStatus::Draft && ($policy['review_before_publish'] ?? false) === true) {
                    $actualTarget = ContentStatus::Review;
                    $resultStatus = 'pending_review';
                }
            } else {
                $normalizedPublishAt = null;
            }

            $oldVersion = $content['version'];
            $content['status'] = $actualTarget->value;
            $content['publish_at'] = $actualTarget === ContentStatus::Scheduled ? $normalizedPublishAt : null;
            $content['review_approved_at'] = $actualTarget === ContentStatus::Scheduled && $current === ContentStatus::Review
                ? self::now() : null;
            if ($actualTarget === ContentStatus::Published) {
                $content['published_values'] = $content['values'];
                $content['published_slug'] = $content['slug'];
                $content['published_at'] = self::now();
                $content['review_approved_at'] = $current === ContentStatus::Review ? self::now() : null;
                $siteRevision = $this->store->incrementDataRevision($actor->siteId);
                $content['published_version'] = $content['version'] + 1;
                $content['published_revision_id'] = null;
            } elseif ($actualTarget === ContentStatus::Draft && $current === ContentStatus::Published) {
                $content['published_values'] = null;
                $content['published_slug'] = null;
                $content['published_version'] = null;
                $content['published_revision_id'] = null;
                $content['published_at'] = null;
                $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            } elseif ($actualTarget === ContentStatus::Draft && $current === ContentStatus::Archived) {
                $content['published_values'] = null;
                $content['published_slug'] = null;
                $content['published_version'] = null;
                $content['published_revision_id'] = null;
                $content['published_at'] = null;
            } elseif ($actualTarget === ContentStatus::Archived && $current === ContentStatus::Published) {
                $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            }
            $content['version'] = $oldVersion + 1;
            $content['updated_at'] = self::now();
            $revisionId = bin2hex(random_bytes(16));
            $content['current_revision_id'] = $revisionId;
            if ($actualTarget === ContentStatus::Published) {
                $content['published_revision_id'] = $revisionId;
            }
            $this->store->saveContent($content, $oldVersion);
            $this->store->appendRevision($this->revision($content, $actor, $revisionId,
                'Transition ' . $current->value . ' → ' . $actualTarget->value, $actor->source));
            $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId,
                'content.' . $actualTarget->value, 'content', $contentId, 'allowed', ['version' => $content['version']]);
            $siteRevision ??= $this->store->incrementDataRevision($actor->siteId);
            return $this->response($content, $resultStatus, $siteRevision ?? null);
        });
    }

    /** @return array<string, mixed> */
    public function preview(ActorContext $actor, string $contentId): array
    {
        $this->authorization->require($actor, 'content.preview');
        $content = $this->store->findContent($actor->siteId, $contentId)
            ?? throw new ContentNotFound('Content does not exist in this site.');
        if (!$this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.read_draft')
            && $content['created_by_id'] !== $actor->actorId) {
            throw new ContentNotFound('Content does not exist in this site.');
        }
        $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId,
            'content.preview', 'content', $contentId, 'allowed', ['version' => $content['version']]);
        $content['preview'] = true;
        return $content;
    }

    /** @return array<string, mixed> */
    public function restoreRevision(
        ActorContext $actor,
        string $contentId,
        string $revisionId,
        int $expectedVersion,
        string $requestId,
    ): array {
        return $this->mutate($actor, $requestId, [
            'op' => 'restore', 'content_id' => $contentId, 'revision_id' => $revisionId,
            'expected_version' => $expectedVersion,
        ], function () use ($actor, $contentId, $revisionId, $expectedVersion): array {
            $this->authorization->require($actor, 'content.restore_revision');
            $content = $this->load($actor, $contentId);
            $this->assertVersion($content, $expectedVersion);
            $revision = $this->store->findRevision($actor->siteId, $contentId, $revisionId);
            if ($revision === null || !is_array($revision['snapshot'] ?? null)) {
                throw new ContentNotFound('Revision does not belong to this content.');
            }
            $snapshot = $revision['snapshot'];
            $rawDefinition = $this->store->findContentType($actor->siteId, $content['type_id']);
            if ($rawDefinition === null) {
                throw new ContentNotFound('Content type is no longer enabled.');
            }
            $values = $snapshot['values'] ?? null;
            if (!is_array($values)) {
                throw new \UnexpectedValueException('Revision snapshot is malformed.');
            }
            (new ContentTypeDefinition($rawDefinition))->validateValues($values);

            $oldVersion = $content['version'];
            $content['values'] = $values;
            $content['slug'] = $this->slugFrom($values, $content['content_id']);
            $wasArchived = $content['status'] === 'archived';
            $content['status'] = 'draft';
            if ($wasArchived) {
                $content['published_values'] = null;
                $content['published_slug'] = null;
                $content['published_version'] = null;
                $content['published_revision_id'] = null;
                $content['published_at'] = null;
            }
            $content['publish_at'] = null;
            $content['review_approved_at'] = null;
            $content['version'] = $oldVersion + 1;
            $content['updated_at'] = self::now();
            $newRevisionId = bin2hex(random_bytes(16));
            $content['current_revision_id'] = $newRevisionId;
            $this->store->saveContent($content, $oldVersion);
            $this->store->appendRevision($this->revision($content, $actor, $newRevisionId,
                'Restauration de la révision ' . $revisionId, $actor->source));
            $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId,
                'content.restore_revision', 'content', $contentId, 'allowed', ['revision_id' => $revisionId]);
            $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            return $this->response($content, 'applied', $siteRevision);
        });
    }

    /** @return array<string, mixed> */
    private function mutate(ActorContext $actor, string $requestId, array $request, callable $operation): array
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $requestId)) {
            throw new \InvalidArgumentException('client_request_id must be 8–128 safe characters.');
        }
        $requestHash = hash('sha256', self::canonicalJson($request));
        try {
            return $this->store->transaction(function () use ($actor, $requestId, $requestHash, $operation): array {
                $existing = $this->store->findIdempotency($actor->siteId, $actor->actorId, $requestId);
                if ($existing !== null) {
                    if (!hash_equals($existing['request_hash'], $requestHash)) {
                        throw new IdempotencyConflict('client_request_id was already used with a different request.');
                    }
                    if (!is_array($existing['response'] ?? null)) {
                        throw new IdempotencyConflict('This client_request_id is already being processed.');
                    }
                    return $existing['response'];
                }
                if (!$this->store->claimIdempotency($actor->siteId, $actor->actorId, $requestId, $requestHash)) {
                    $raced = $this->store->findIdempotency($actor->siteId, $actor->actorId, $requestId);
                    if ($raced === null || !hash_equals($raced['request_hash'], $requestHash)
                        || !is_array($raced['response'] ?? null)) {
                        throw new IdempotencyConflict('client_request_id conflicts with another request.');
                    }
                    return $raced['response'];
                }
                $response = $operation();
                $this->store->completeIdempotency(
                    $actor->siteId, $actor->actorId, $requestId,
                    json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                );
                return $response;
            });
        } catch (AccessDenied $error) {
            $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId,
                'authorization.denied', 'content', $request['content_id'] ?? null,
                'denied', ['operation' => $request['op'] ?? 'unknown']);
            throw $error;
        }
    }

    private function load(ActorContext $actor, string $contentId): array
    {
        $content = $this->store->findContentForUpdate($actor->siteId, $contentId);
        if ($content === null) {
            throw new ContentNotFound('Content does not exist in this site.');
        }
        return $content;
    }

    private function assertVersion(array $content, int $expectedVersion): void
    {
        if ($expectedVersion < 1 || $content['version'] !== $expectedVersion) {
            throw new VersionConflict('Content version is stale.');
        }
    }

    private function permissionFor(ContentStatus $from, ContentStatus $to): string
    {
        return match (true) {
            $to === ContentStatus::Review => 'content.submit_for_review',
            $to === ContentStatus::Scheduled => 'content.schedule',
            $to === ContentStatus::Published => 'content.publish',
            $to === ContentStatus::Archived => 'content.archive',
            $from === ContentStatus::Published && $to === ContentStatus::Draft => 'content.unpublish',
            default => 'content.update',
        };
    }

    private function transitionAllowed(ContentStatus $from, ContentStatus $to): bool
    {
        return match ($from) {
            ContentStatus::Draft => in_array($to, [ContentStatus::Review, ContentStatus::Scheduled, ContentStatus::Published, ContentStatus::Archived], true),
            ContentStatus::Review => in_array($to, [ContentStatus::Draft, ContentStatus::Scheduled, ContentStatus::Published, ContentStatus::Archived], true),
            ContentStatus::Scheduled => in_array($to, [ContentStatus::Draft, ContentStatus::Review, ContentStatus::Archived], true),
            ContentStatus::Published => in_array($to, [ContentStatus::Draft, ContentStatus::Archived], true),
            ContentStatus::Archived => $to === ContentStatus::Draft,
        };
    }

    private function futureUtc(string $value): string
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            throw new InvalidTransition('publish_at must be an ISO 8601 date-time with an explicit timezone.');
        }
        try {
            $date = new DateTimeImmutable($value);
        } catch (\Throwable $error) {
            throw new InvalidTransition('publish_at must be a valid ISO 8601 date-time.', previous: $error);
        }
        $utc = $date->setTimezone(new DateTimeZone('UTC'));
        if ($utc <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new InvalidTransition('publish_at must be in the future.');
        }
        return $utc->format('Y-m-d H:i:s.u');
    }

    private function slugFrom(array $values, string $fallback): string
    {
        $slug = $values['slug'] ?? $fallback;
        if (!is_string($slug) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) > 190) {
            throw new \InvalidArgumentException('slug must use lowercase letters, numbers and single hyphens.');
        }
        return $slug;
    }

    private function revision(array $content, ActorContext $actor, string $revisionId, string $summary, string $source): array
    {
        return [
            'site_id' => $content['site_id'],
            'revision_id' => $revisionId,
            'content_id' => $content['content_id'],
            'version' => $content['version'],
            'author_id' => $actor->actorId,
            'source' => $source,
            'change_summary' => mb_substr($summary, 0, 500),
            'snapshot' => [
                'type' => $content['type_id'], 'slug' => $content['slug'],
                'status' => $content['status'], 'values' => $content['values'],
                'published_slug' => $content['published_slug'],
                'published_version' => $content['published_version'],
                'published_revision_id' => $content['published_revision_id'],
                'published_values' => $content['published_values'], 'publish_at' => $content['publish_at'],
                'review_approved_at' => $content['review_approved_at'], 'published_at' => $content['published_at'],
            ],
            'created_at' => $content['updated_at'],
        ];
    }

    private function response(array $content, string $resultStatus, ?int $siteRevision = null): array
    {
        $response = [
            'status' => $resultStatus,
            'content' => [
                'id' => $content['content_id'], 'site_id' => $content['site_id'],
                'type' => $content['type_id'], 'slug' => $content['slug'],
                'status' => $content['status'], 'values' => $content['values'],
                'version' => $content['version'], 'current_revision_id' => $content['current_revision_id'],
                'publish_at' => $content['publish_at'], 'published_at' => $content['published_at'],
                'created_at' => $content['created_at'], 'updated_at' => $content['updated_at'],
            ],
        ];
        if ($siteRevision !== null) {
            $response['site_revision'] = $siteRevision;
        }
        return $response;
    }

    private function publishedProjection(array $content): array
    {
        if ($content['published_values'] === null || $content['status'] === 'archived'
            || $content['published_slug'] === null || $content['published_revision_id'] === null) {
            throw new ContentNotFound('No public version exists for this content.');
        }
        $content['slug'] = $content['published_slug'];
        $content['status'] = 'published';
        $content['values'] = $content['published_values'];
        $content['version'] = $content['published_version'];
        $content['current_revision_id'] = $content['published_revision_id'];
        $content['updated_at'] = $content['published_at'];
        $content['publish_at'] = null;
        unset($content['published_values'], $content['published_slug'], $content['published_version'], $content['published_revision_id'], $content['review_approved_at']);
        return $content;
    }

    private function isOwnWorkingCopy(array $content, ActorContext $actor): bool
    {
        return $content['created_by_id'] === $actor->actorId
            && in_array($content['status'], ['draft', 'review'], true);
    }

    private static function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    private static function canonicalJson(mixed $value): string
    {
        if (is_array($value)) {
            if (!array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$child) {
                $child = json_decode(self::canonicalJson($child), true, 512, JSON_THROW_ON_ERROR);
            }
            unset($child);
        }
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
