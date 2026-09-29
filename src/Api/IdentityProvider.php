<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

/**
 * Site-owned identity integration. Implementations must verify credentials, keep challenges
 * short-lived/one-use/attempt-limited, rate-limit failures, and return only a verified member.
 * The provider, not this CMS, owns passwords, TOTP secrets, and pairing-code generation.
 */
interface IdentityProvider
{
    public function start(string $username, string $password, string $deviceName): AuthenticationStartResult;

    /** Must consume the challenge and return the device name retained during start(). */
    public function verify(string $challengeId, string $method, string $code): VerifiedLogin;

    /** Must consume a short-lived, one-use pairing code and return its verified member. */
    public function pair(string $code): AuthenticatedMember;
}
