<?php
declare(strict_types=1);

namespace Dendrila\Cms\Seo;

interface SeoRepository
{
    /** @return array<string,mixed>|null */
    public function find(string $siteId, string $contentId): ?array;

    /** @return array<string,mixed>|null */
    public function findForUpdate(string $siteId, string $contentId): ?array;

    /** @param array<string,mixed> $record */
    public function insert(array $record): void;

    /** @param array<string,mixed> $record */
    public function save(array $record, int $expectedVersion): void;
}
