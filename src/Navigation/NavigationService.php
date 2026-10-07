<?php
declare(strict_types=1);

namespace Dendrila\Cms\Navigation;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Storage\CmsStore;

final readonly class NavigationService
{
    public function __construct(
        private NavigationRepository $navigation,
        private CmsStore $store,
        private AuthorizationService $authorization,
    ) {}

    /** @return list<array<string,mixed>> */
    public function list(ActorContext $actor): array
    {
        $this->authorization->require($actor, 'navigation.read');
        return array_map(
            static fn (array $menu): array => NavigationDefinition::normalizeMenu($menu),
            $this->navigation->list($actor->siteId),
        );
    }

    /** @return array<string,mixed> */
    public function get(ActorContext $actor, string $menuId): array
    {
        $this->authorization->require($actor, 'navigation.read');
        $menuId = NavigationDefinition::identifier($menuId, 'navigation menu id');
        $menu = $this->navigation->find($actor->siteId, $menuId);
        if ($menu === null) {
            throw new NavigationNotFound('Navigation menu does not exist.');
        }
        return NavigationDefinition::normalizeMenu($menu);
    }

    /**
     * expectedVersion=0 creates a new menu. Existing menus require their current version.
     * @param list<array<string,mixed>> $items
     * @return array{menu:array<string,mixed>,site_revision:int}
     */
    public function save(
        ActorContext $actor,
        string $menuId,
        string $label,
        string $location,
        array $items,
        int $expectedVersion,
    ): array {
        $this->authorization->require($actor, 'navigation.manage');
        $menuId = NavigationDefinition::identifier($menuId, 'navigation menu id');
        $location = NavigationDefinition::identifier($location, 'navigation location');
        $items = NavigationDefinition::normalizeItems($items);
        if (trim($label) === '' || mb_strlen($label) > 190 || $expectedVersion < 0) {
            throw new \InvalidArgumentException('Invalid navigation menu mutation.');
        }
        $label = trim($label);

        return $this->store->transaction(function () use (
            $actor, $menuId, $label, $location, $items, $expectedVersion
        ): array {
            $current = $this->navigation->findForUpdate($actor->siteId, $menuId);
            if ($current === null && $expectedVersion !== 0) {
                throw new VersionConflict('Navigation menu no longer exists.');
            }
            if ($current !== null && (int) $current['version'] !== $expectedVersion) {
                throw new VersionConflict('Navigation menu version is stale.');
            }

            $occupied = $this->navigation->findByLocation($actor->siteId, $location);
            if ($occupied !== null && $occupied['menu_id'] !== $menuId) {
                throw new \InvalidArgumentException('Navigation location is already assigned to another menu.');
            }

            $version = $current === null ? 1 : $expectedVersion + 1;
            $menu = NavigationDefinition::normalizeMenu([
                'site_id' => $actor->siteId,
                'menu_id' => $menuId,
                'label' => $label,
                'location' => $location,
                'items' => $items,
                'version' => $version,
                'created_at' => $current['created_at'] ?? null,
                'updated_at' => null,
            ]);

            if ($current === null) {
                $this->navigation->insert($menu);
            } else {
                $this->navigation->save($menu, $expectedVersion);
            }
            $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            $this->store->appendAudit(
                $actor->siteId,
                $actor->actorId,
                $actor->deviceId,
                $current === null ? 'navigation.create' : 'navigation.update',
                'navigation_menu',
                $menuId,
                'allowed',
                ['version' => $version, 'location' => $location],
            );
            return ['menu' => $menu, 'site_revision' => $siteRevision];
        });
    }

    /** @return array{menu_id:string,site_revision:int} */
    public function delete(ActorContext $actor, string $menuId, int $expectedVersion): array
    {
        $this->authorization->require($actor, 'navigation.manage');
        $menuId = NavigationDefinition::identifier($menuId, 'navigation menu id');
        if ($expectedVersion < 1) {
            throw new \InvalidArgumentException('Invalid navigation version.');
        }

        return $this->store->transaction(function () use ($actor, $menuId, $expectedVersion): array {
            $current = $this->navigation->findForUpdate($actor->siteId, $menuId);
            if ($current === null) {
                throw new NavigationNotFound('Navigation menu does not exist.');
            }
            if ((int) $current['version'] !== $expectedVersion) {
                throw new VersionConflict('Navigation menu version is stale.');
            }
            $this->navigation->delete($actor->siteId, $menuId, $expectedVersion);
            $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            $this->store->appendAudit(
                $actor->siteId,
                $actor->actorId,
                $actor->deviceId,
                'navigation.delete',
                'navigation_menu',
                $menuId,
                'allowed',
                ['version' => $expectedVersion, 'location' => $current['location']],
            );
            return ['menu_id' => $menuId, 'site_revision' => $siteRevision];
        });
    }
}
