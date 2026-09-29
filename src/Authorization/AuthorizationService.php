<?php
declare(strict_types=1);

namespace LePotager\Cms\Authorization;

use LePotager\Cms\Storage\CmsStore;

final readonly class AuthorizationService
{
    public function __construct(private CmsStore $store)
    {
    }

    public function require(ActorContext $actor, string $permission): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/', $permission)) {
            throw new \InvalidArgumentException('Malformed permission name.');
        }

        if (!$this->store->actorHasPermission($actor->siteId, $actor->actorId, $permission)) {
            throw new AccessDenied('The actor is not allowed to perform this action.');
        }
    }
}
