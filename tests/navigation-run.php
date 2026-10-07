<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Navigation\NavigationDefinition;
use Dendrila\Cms\Navigation\NavigationNotFound;
use Dendrila\Cms\Navigation\NavigationRepository;
use Dendrila\Cms\Navigation\NavigationService;
use Dendrila\Cms\Tests\Support\InMemoryCmsStore;

function navigationCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function navigationExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        navigationCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$repository = new class implements NavigationRepository {
    public array $menus = [];

    public function find(string $siteId, string $menuId): ?array
    {
        return $this->menus[$siteId][$menuId] ?? null;
    }

    public function findForUpdate(string $siteId, string $menuId): ?array
    {
        return $this->find($siteId, $menuId);
    }

    public function findByLocation(string $siteId, string $location): ?array
    {
        foreach ($this->menus[$siteId] ?? [] as $menu) {
            if ($menu['location'] === $location) return $menu;
        }
        return null;
    }

    public function list(string $siteId): array
    {
        $menus = array_values($this->menus[$siteId] ?? []);
        usort($menus, static fn (array $a, array $b): int => [$a['location'], $a['menu_id']] <=> [$b['location'], $b['menu_id']]);
        return $menus;
    }

    public function insert(array $menu): void
    {
        $this->menus[$menu['site_id']][$menu['menu_id']] = $menu;
    }

    public function save(array $menu, int $expectedVersion): void
    {
        $current = $this->menus[$menu['site_id']][$menu['menu_id']] ?? null;
        if ($current === null || $current['version'] !== $expectedVersion) {
            throw new VersionConflict('Navigation menu version is stale.');
        }
        $this->menus[$menu['site_id']][$menu['menu_id']] = $menu;
    }

    public function delete(string $siteId, string $menuId, int $expectedVersion): void
    {
        $current = $this->menus[$siteId][$menuId] ?? null;
        if ($current === null || $current['version'] !== $expectedVersion) {
            throw new VersionConflict('Navigation menu version is stale.');
        }
        unset($this->menus[$siteId][$menuId]);
    }
};

$store = new InMemoryCmsStore();
$store->permissions['site-a']['editor-1'] = ['navigation.read', 'navigation.manage'];
$store->permissions['site-a']['viewer-1'] = ['navigation.read'];
$service = new NavigationService($repository, $store, new AuthorizationService($store));
$editor = new ActorContext('site-a', 'editor-1', 'device-1');

$created = $service->save($editor, 'main', 'Navigation principale', 'primary', [
    ['id' => 'home', 'label' => 'Accueil', 'url' => '/'],
    ['id' => 'contact', 'label' => 'Contact', 'url' => '/contact', 'visible' => false],
], 0);
navigationCheck($created['menu']['version'] === 1, 'Creating a menu starts at version 1.');
navigationCheck($created['menu']['items'][0]['visible'] === true, 'Visible defaults to true.');
navigationCheck($created['site_revision'] === 2, 'Navigation mutation advances the site data revision.');
navigationCheck($service->get($editor, 'main')['location'] === 'primary', 'Authorized editor can read a menu.');
navigationCheck(count($service->list($editor)) === 1, 'Authorized editor can list site navigation.');
navigationCheck(($store->audit[0]['action'] ?? null) === 'navigation.create', 'Navigation creation is audited.');

$updated = $service->save($editor, 'main', 'Menu principal', 'primary', [
    ['id' => 'home', 'label' => 'Accueil', 'url' => '/'],
    ['id' => 'booking', 'label' => 'Réserver', 'url' => '/reserver'],
    ['id' => 'external', 'label' => 'Ressource', 'url' => 'https://example.test/resource'],
], 1);
navigationCheck($updated['menu']['version'] === 2, 'Updating a menu increments its version.');
navigationCheck(array_column($updated['menu']['items'], 'id') === ['home', 'booking', 'external'],
    'Navigation item order is preserved.');

navigationExpect(VersionConflict::class, fn () => $service->save(
    $editor, 'main', 'Stale', 'primary', [], 1,
));
navigationExpect(InvalidArgumentException::class, fn () => $service->save(
    $editor, 'unsafe', 'Unsafe', 'secondary',
    [['id' => 'x', 'label' => 'X', 'url' => 'javascript:alert(1)']], 0,
));
navigationExpect(InvalidArgumentException::class, fn () => NavigationDefinition::normalizeItems([
    ['id' => 'same', 'label' => 'A', 'url' => '/a'],
    ['id' => 'same', 'label' => 'B', 'url' => '/b'],
]));
navigationExpect(InvalidArgumentException::class, fn () => $service->save(
    $editor, 'second', 'Second', 'primary', [], 0,
));

$viewer = new ActorContext('site-a', 'viewer-1');
navigationCheck($service->get($viewer, 'main')['version'] === 2, 'Read-only navigation permission can read.');
navigationExpect(AccessDenied::class, fn () => $service->save($viewer, 'main', 'No', 'primary', [], 2));

$foreign = new ActorContext('site-b', 'editor-1');
$store->permissions['site-b']['editor-1'] = ['navigation.read'];
navigationExpect(NavigationNotFound::class, fn () => $service->get($foreign, 'main'));

$deleted = $service->delete($editor, 'main', 2);
navigationCheck($deleted['site_revision'] === 4, 'Deleting a menu advances the site data revision.');
navigationExpect(NavigationNotFound::class, fn () => $service->get($editor, 'main'));
navigationCheck(($store->audit[array_key_last($store->audit)]['action'] ?? null) === 'navigation.delete',
    'Navigation deletion is audited.');

echo "Navigation core tests passed.\n";
