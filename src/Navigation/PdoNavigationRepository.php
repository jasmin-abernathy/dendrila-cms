<?php
declare(strict_types=1);

namespace Dendrila\Cms\Navigation;

use Dendrila\Cms\Content\VersionConflict;
use PDO;

final readonly class PdoNavigationRepository implements NavigationRepository
{
    public function __construct(private PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function find(string $siteId, string $menuId): ?array
    {
        return $this->read(
            'SELECT site_id, menu_id, label, location, items_json, version, created_at, updated_at
             FROM navigation_menus WHERE site_id = :site_id AND menu_id = :menu_id LIMIT 1',
            ['site_id' => $siteId, 'menu_id' => $menuId],
        );
    }

    public function findForUpdate(string $siteId, string $menuId): ?array
    {
        return $this->read(
            'SELECT site_id, menu_id, label, location, items_json, version, created_at, updated_at
             FROM navigation_menus WHERE site_id = :site_id AND menu_id = :menu_id LIMIT 1 FOR UPDATE',
            ['site_id' => $siteId, 'menu_id' => $menuId],
        );
    }

    public function findByLocation(string $siteId, string $location): ?array
    {
        return $this->read(
            'SELECT site_id, menu_id, label, location, items_json, version, created_at, updated_at
             FROM navigation_menus WHERE site_id = :site_id AND location = :location LIMIT 1',
            ['site_id' => $siteId, 'location' => $location],
        );
    }

    public function list(string $siteId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT site_id, menu_id, label, location, items_json, version, created_at, updated_at
             FROM navigation_menus WHERE site_id = :site_id ORDER BY location ASC, menu_id ASC'
        );
        $statement->execute(['site_id' => $siteId]);
        return array_map(fn (array $row): array => $this->decode($row), $statement->fetchAll());
    }

    public function insert(array $menu): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO navigation_menus
             (site_id, menu_id, label, location, items_json, version, created_at, updated_at)
             VALUES (:site_id, :menu_id, :label, :location, :items_json, :version, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))'
        );
        $statement->execute([
            'site_id' => $menu['site_id'],
            'menu_id' => $menu['menu_id'],
            'label' => $menu['label'],
            'location' => $menu['location'],
            'items_json' => self::encode($menu['items']),
            'version' => $menu['version'],
        ]);
    }

    public function save(array $menu, int $expectedVersion): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE navigation_menus
             SET label = :label, location = :location, items_json = :items_json,
                 version = :version, updated_at = UTC_TIMESTAMP(6)
             WHERE site_id = :site_id AND menu_id = :menu_id AND version = :expected_version'
        );
        $statement->execute([
            'label' => $menu['label'],
            'location' => $menu['location'],
            'items_json' => self::encode($menu['items']),
            'version' => $menu['version'],
            'site_id' => $menu['site_id'],
            'menu_id' => $menu['menu_id'],
            'expected_version' => $expectedVersion,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new VersionConflict('Navigation menu version is stale.');
        }
    }

    public function delete(string $siteId, string $menuId, int $expectedVersion): void
    {
        $statement = $this->pdo->prepare(
            'DELETE FROM navigation_menus
             WHERE site_id = :site_id AND menu_id = :menu_id AND version = :expected_version'
        );
        $statement->execute([
            'site_id' => $siteId,
            'menu_id' => $menuId,
            'expected_version' => $expectedVersion,
        ]);
        if ($statement->rowCount() !== 1) {
            throw new VersionConflict('Navigation menu version is stale.');
        }
    }

    private function read(string $sql, array $parameters): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch();
        return $row === false ? null : $this->decode($row);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function decode(array $row): array
    {
        $items = json_decode((string) $row['items_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items)) {
            throw new \RuntimeException('Stored navigation items are invalid.');
        }
        unset($row['items_json']);
        $row['items'] = $items;
        $row['version'] = (int) $row['version'];
        return NavigationDefinition::normalizeMenu($row);
    }

    private static function encode(array $items): string
    {
        return json_encode($items, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
