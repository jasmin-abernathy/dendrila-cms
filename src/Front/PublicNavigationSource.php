<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

interface PublicNavigationSource
{
    /** @return list<array{label:string,url:string}> */
    public function items(string $siteId, string $location = 'primary'): array;
}
