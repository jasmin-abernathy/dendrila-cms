<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use Dendrila\Cms\Authorization\ActorContext;

interface PublicPageSource
{
    public function pages(ActorContext $actor, string $mode): array;
}
