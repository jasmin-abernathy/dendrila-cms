<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

use DateTimeImmutable;

interface PairingCodeStore
{
    public function issue(string $siteId, string $actorId, string $code, DateTimeImmutable $expiresAt): void;

    public function consumeHash(string $codeHash): ?AuthenticatedMember;
}
