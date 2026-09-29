<?php
declare(strict_types=1);

namespace LePotager\Cms\Authorization;

/** Identity supplied only after the MMW adapter has verified the device token. */
final readonly class ActorContext
{
    public function __construct(
        public string $siteId,
        public string $actorId,
        public ?string $deviceId = null,
        public string $source = 'web',
    ) {
        if ($siteId === '' || $actorId === '' || !in_array($source, ['web', 'mmw', 'import', 'system'], true)) {
            throw new \InvalidArgumentException('An authenticated actor needs a site and actor id.');
        }
    }
}
