<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

final readonly class AuthenticatedMember
{
    public function __construct(public string $siteId, public string $actorId)
    {
        if ($siteId === '' || $actorId === '') {
            throw new \InvalidArgumentException('Authenticated identity needs a site and actor id.');
        }
    }
}
