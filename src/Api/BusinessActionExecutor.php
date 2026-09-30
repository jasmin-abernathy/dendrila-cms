<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;

/**
 * Site-owned connector for item_action. It must re-check item ownership, current state,
 * permissions and idempotency against the business system before applying any operation.
 */
interface BusinessActionExecutor
{
    /** @param array<string, mixed> $module @param array<string, mixed> $payload @return array<string, mixed> */
    public function execute(ActorContext $actor, array $module, array $payload, string $requestId): array;
}
