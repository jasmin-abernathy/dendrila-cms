<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

use DateTimeImmutable;

final readonly class PdoPairingIdentityProvider implements IdentityProvider
{
    public function __construct(private PairingCodeStore $pairingCodes)
    {
    }

    public function start(string $username, string $password, string $deviceName): AuthenticationStartResult
    {
        throw new AuthenticationFailure('Authentication failed.');
    }

    public function verify(string $challengeId, string $method, string $code): VerifiedLogin
    {
        throw new AuthenticationFailure('Authentication failed.');
    }

    public function pair(string $code): AuthenticatedMember
    {
        if (!preg_match('/^[0-9]{6,12}$/', $code)) {
            throw new AuthenticationFailure('Authentication failed.');
        }

        $member = $this->pairingCodes->consumeHash(hash('sha256', $code));
        if ($member === null) {
            throw new AuthenticationFailure('Authentication failed.');
        }
        return $member;
    }

    public function issuePairingCode(
        string $siteId,
        string $actorId,
        string $code,
        DateTimeImmutable $expiresAt,
    ): void {
        if (!preg_match('/^[0-9]{12}$/', $code)) {
            throw new \InvalidArgumentException('Pairing codes must contain exactly 12 digits.');
        }
        $this->pairingCodes->issue($siteId, $actorId, $code, $expiresAt);
    }
}
