<?php
declare(strict_types=1);

namespace Dendrila\Cms\Navigation;

interface NavigationRepository
{
    /** @return array<string,mixed>|null */
    public function find(string $siteId, string $menuId): ?array;

    /** @return array<string,mixed>|null */
    public function findForUpdate(string $siteId, string $menuId): ?array;

    /** @return array<string,mixed>|null */
    public function findByLocation(string $siteId, string $location): ?array;

    /** @return list<array<string,mixed>> */
    public function list(string $siteId): array;

    /** @param array<string,mixed> $menu */
    public function insert(array $menu): void;

    /** @param array<string,mixed> $menu */
    public function save(array $menu, int $expectedVersion): void;

    public function delete(string $siteId, string $menuId, int $expectedVersion): void;
}
