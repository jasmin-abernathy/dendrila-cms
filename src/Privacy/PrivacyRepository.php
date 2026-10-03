<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

interface PrivacyRepository
{
    public function transaction(callable $operation): mixed;

    /** @return array<string, mixed>|null */
    public function findSettings(string $siteId): ?array;

    /** @param array<string, mixed> $settings */
    public function saveSettings(string $siteId, array $settings): void;

    /** @return array<string, mixed>|null */
    public function findLatestCompletedScan(string $siteId): ?array;

    /** @return list<array<string, mixed>> */
    public function listFindings(string $siteId, string $scanId): array;

    /** @param array<string, mixed> $scan @param list<array<string, mixed>> $findings */
    public function saveCompletedScan(array $scan, array $findings): void;

    /** @return array<string, mixed>|null */
    public function findLegalDocument(string $siteId, string $kind): ?array;

    /** @param array<string, mixed> $document */
    public function saveLegalDocument(string $siteId, array $document): void;

    /** @param array<string, mixed> $details */
    public function appendAudit(
        string $siteId,
        ?string $actorId,
        ?string $deviceId,
        string $action,
        ?string $entityId,
        array $details,
    ): void;
}
