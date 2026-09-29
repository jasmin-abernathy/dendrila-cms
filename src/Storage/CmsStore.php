<?php
declare(strict_types=1);

namespace LePotager\Cms\Storage;

interface CmsStore
{
    public function transaction(callable $operation): mixed;

    public function actorHasPermission(string $siteId, string $actorId, string $permission): bool;

    /** @return array<string, mixed>|null */
    public function findContentType(string $siteId, string $typeId): ?array;

    /** @return array<string, mixed> */
    public function findSitePolicy(string $siteId): array;

    public function incrementDataRevision(string $siteId): int;

    /** @return array<string, mixed>|null */
    public function findContentForUpdate(string $siteId, string $contentId): ?array;

    /** @return array<string, mixed>|null */
    public function findContent(string $siteId, string $contentId, bool $includeDrafts = true, ?string $ownDraftActorId = null): ?array;

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function listContents(string $siteId, array $filters, bool $includeDrafts = true, ?string $ownDraftActorId = null): array;

    /** @param array<string, mixed> $content */
    public function insertContent(array $content): void;

    /** @param array<string, mixed> $content */
    public function saveContent(array $content, int $expectedVersion): void;

    /** @param array<string, mixed> $revision */
    public function appendRevision(array $revision): void;

    public function findRevision(string $siteId, string $contentId, string $revisionId): ?array;

    public function findIdempotency(string $siteId, string $actorId, string $requestId): ?array;

    /** @param array<string, mixed> $details */
    public function appendAudit(
        string $siteId,
        ?string $actorId,
        ?string $deviceId,
        string $action,
        string $entityType,
        ?string $entityId,
        string $outcome,
        array $details,
    ): void;

    /** @return list<array<string, mixed>> */
    public function findDuePublications(string $siteId, string $nowUtc, int $limit): array;

    public function claimIdempotency(string $siteId, string $actorId, string $requestId, string $requestHash): bool;

    public function completeIdempotency(string $siteId, string $actorId, string $requestId, string $responseJson): void;
}
