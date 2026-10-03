<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;

interface SiteChangeHandler
{
    /** @param array<string, mixed> $request @return array<string, mixed> */
    public function apply(ActorContext $actor, array $request): array;
}
