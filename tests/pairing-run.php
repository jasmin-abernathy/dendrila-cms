<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Api\AuthenticatedMember;
use LePotager\Cms\Api\AuthenticationFailure;
use LePotager\Cms\Api\AuthenticationStartResult;
use LePotager\Cms\Api\PairingCodeStore;
use LePotager\Cms\Api\PdoPairingIdentityProvider;
use LePotager\Cms\Api\VerifiedLogin;

function pairingCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class InMemoryPairingCodeStore implements PairingCodeStore
{
    /** @var array<string, array{site_id:string, actor_id:string, expires_at:DateTimeImmutable, consumed:bool}> */
    private array $codes = [];

    public function issue(string $siteId, string $actorId, string $code, DateTimeImmutable $expiresAt): void
    {
        $this->codes[hash('sha256', $code)] = [
            'site_id' => $siteId,
            'actor_id' => $actorId,
            'expires_at' => $expiresAt,
            'consumed' => false,
        ];
    }

    public function consumeHash(string $codeHash): ?AuthenticatedMember
    {
        $entry = $this->codes[$codeHash] ?? null;
        if ($entry === null || $entry['consumed'] || $entry['expires_at'] <= new DateTimeImmutable('now')) {
            return null;
        }
        $this->codes[$codeHash]['consumed'] = true;
        return new AuthenticatedMember($entry['site_id'], $entry['actor_id']);
    }
}

$store = new InMemoryPairingCodeStore();
$provider = new PdoPairingIdentityProvider($store);
$store->issue('cabinet-test', 'doctor-test', '012345678901', new DateTimeImmutable('+5 minutes'));
$member = $provider->pair('012345678901');
pairingCheck($member->siteId === 'cabinet-test' && $member->actorId === 'doctor-test',
    'A valid pairing code resolves only its provisioned site member.');

try {
    $provider->pair('012345678901');
    throw new RuntimeException('A pairing code was accepted more than once.');
} catch (AuthenticationFailure) {
    // Expected: pairing codes are one-use.
}

try {
    $provider->pair('not-a-code');
    throw new RuntimeException('A malformed pairing code was accepted.');
} catch (AuthenticationFailure) {
    // Expected: the protocol exposes a generic authentication failure.
}

$store->issue('cabinet-test', 'doctor-test', '999999999999', new DateTimeImmutable('-1 minute'));
try {
    $provider->pair('999999999999');
    throw new RuntimeException('An expired pairing code was accepted.');
} catch (AuthenticationFailure) {
    // Expected: expired codes fail closed.
}

foreach ([
    fn () => $provider->start('doctor', 'password', 'device'),
    fn () => $provider->verify('challenge_identifier_123', 'totp', '123456'),
] as $unsupportedLogin) {
    try {
        $unsupportedLogin();
        throw new RuntimeException('Password/TOTP auth was enabled by the pairing-only provider.');
    } catch (AuthenticationFailure) {
        // Expected: this provider intentionally supports pairing only.
    }
}

try {
    $provider->issuePairingCode('cabinet-test', 'doctor-test', '12345678', new DateTimeImmutable('+5 minutes'));
    throw new RuntimeException('A weak short code was issued.');
} catch (InvalidArgumentException) {
    // Expected: issuer only stores 12-digit codes.
}

echo "Pairing identity tests passed.\n";
