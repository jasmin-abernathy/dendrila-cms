<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use Dendrila\Cms\Navigation\NavigationDefinition;
use Dendrila\Cms\Navigation\PdoNavigationRepository;
use PDO;

final readonly class PdoPublicNavigationSource implements PublicNavigationSource
{
    private PdoNavigationRepository $navigation;

    public function __construct(PDO $pdo)
    {
        $this->navigation = new PdoNavigationRepository($pdo);
    }

    public function items(string $siteId, string $location = 'primary'): array
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D', $siteId) !== 1) {
            throw new \InvalidArgumentException('Invalid public navigation site id.');
        }
        $location = NavigationDefinition::identifier($location, 'navigation location');
        $menu = $this->navigation->findByLocation($siteId, $location);
        if ($menu === null) {
            return [];
        }
        $safe = NavigationDefinition::normalizeMenu($menu);
        $items = [];
        foreach ($safe['items'] as $item) {
            if ($item['visible'] !== true) {
                continue;
            }
            $items[] = ['label' => $item['label'], 'url' => $item['url']];
        }
        return $items;
    }
}
