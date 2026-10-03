<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;

interface SiteReadModel
{
    /** @return array<string, mixed> */
    public function configuration(ActorContext $actor): array;

    /** @return array<string, mixed> */
    public function snapshot(ActorContext $actor): array;
}
