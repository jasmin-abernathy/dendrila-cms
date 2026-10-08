<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Api\AuthenticatedMember;
use Dendrila\Cms\Api\AuthenticationFailure;
use Dendrila\Cms\Api\AuthenticationService;
use Dendrila\Cms\Api\IdentityRuntimeFactory;
use Dendrila\Cms\Api\LegacyMmwDeviceAuthenticator;
use Dendrila\Cms\Api\LegacyMmwDeviceTokenIssuer;
use Dendrila\Cms\Api\LegacyMmwPairingIdentityProvider;
use Dendrila\Cms\Api\PdoDeviceAuthenticator;
use Dendrila\Cms\Api\Unauthorized;
use Dendrila\Cms\Http\Request;

function identityCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function identityExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        identityCheck($error instanceof $class, "Expected {$class}, got " . $error::class . ': ' . $error->getMessage());
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$dsn = getenv('DB_DSN') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
if ($dsn === '' || $username === '') {
    fwrite(STDERR, "DB_DSN and DB_USERNAME are required.\n");
    exit(2);
}
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS site_admins (
        id VARCHAR(32) PRIMARY KEY,
        username VARCHAR(190) NOT NULL UNIQUE,
        email VARCHAR(254) NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(32) NOT NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS mmw_pair_codes (
        id CHAR(64) PRIMARY KEY,
        code_hash CHAR(64) NOT NULL UNIQUE,
        actor_id VARCHAR(32) NOT NULL,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME NULL,
        created_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS mmw_devices (
        device_id CHAR(32) PRIMARY KEY,
        actor_id VARCHAR(32) NOT NULL,
        device_name VARCHAR(120) NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL,
        last_seen_at DATETIME NULL,
        revoked_at DATETIME NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$actorId = 'legacy-owner';
$secret = 'identity-test-secret-0123456789abcdef';
$pdo->prepare('DELETE FROM memberships WHERE site_id = ? AND actor_id = ?')->execute(['site-native', $actorId]);
$pdo->prepare('DELETE FROM mmw_devices WHERE actor_id = ?')->execute([$actorId]);
$pdo->prepare('DELETE FROM mmw_pair_codes WHERE actor_id = ?')->execute([$actorId]);
$pdo->prepare('DELETE FROM site_admins WHERE id = ?')->execute([$actorId]);
$pdo->prepare('DELETE FROM auth_rate_limits WHERE site_id = ?')->execute(['site-native']);
$pdo->prepare(
    'INSERT INTO site_admins (id, username, email, password_hash, role, created_at)
     VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())'
)->execute([$actorId, 'legacy-owner', 'owner@example.test', password_hash('unused-password', PASSWORD_ARGON2ID), 'owner']);

$insertCode = function (string $code, string $id, string $expirySql) use ($pdo, $actorId, $secret): void {
    $statement = $pdo->prepare(
        "INSERT INTO mmw_pair_codes (id, code_hash, actor_id, expires_at, consumed_at, created_at)
         VALUES (?, ?, ?, {$expirySql}, NULL, UTC_TIMESTAMP())"
    );
    $statement->execute([$id, hash_hmac('sha256', $code, $secret), $actorId]);
};

$provider = new LegacyMmwPairingIdentityProvider($pdo, 'site-native', $secret, ['owner' => 'owner']);
identityExpect(AuthenticationFailure::class, fn () => $provider->start('legacy-owner', 'unused-password', 'Browser'));
identityExpect(AuthenticationFailure::class, fn () => $provider->verify('challenge_identifier_123', 'totp', '123456'));

identityExpect(AuthenticationFailure::class, fn () => $provider->pair('00000000'));
identityCheck((int) $pdo->query(
    "SELECT failures FROM auth_rate_limits WHERE site_id = 'site-native' AND scope = 'legacy_mmw_pairing'"
)->fetchColumn() === 1, 'A failed pairing attempt is rate-limited persistently.');

$insertCode('12345678', str_repeat('a', 64), 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)');
$member = $provider->pair('12345678');
identityCheck($member instanceof AuthenticatedMember
    && $member->siteId === 'site-native' && $member->actorId === $actorId,
    'A valid legacy pairing code resolves the configured Dendrila site and actor.');
identityCheck($pdo->query(
    "SELECT COUNT(*) FROM memberships WHERE site_id = 'site-native' AND actor_id = 'legacy-owner' AND role_id = 'owner'"
)->fetchColumn() === 1, 'Verified legacy role is synchronized to the mapped Dendrila role.');
identityCheck((int) $pdo->query(
    "SELECT COUNT(*) FROM auth_rate_limits WHERE site_id = 'site-native' AND scope = 'legacy_mmw_pairing'"
)->fetchColumn() === 0, 'Successful pairing clears previous failures.');
identityExpect(AuthenticationFailure::class, fn () => $provider->pair('12345678'));

$insertCode('23456789', str_repeat('b', 64), 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)');
identityExpect(AuthenticationFailure::class, fn () => $provider->pair('23456789'));

$insertCode('34567890', str_repeat('c', 64), 'DATE_ADD(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)');
$issuer = new LegacyMmwDeviceTokenIssuer($pdo, 'site-native', $secret);
$authentication = new AuthenticationService($provider, $issuer);
$response = $authentication->pair(['code' => '34567890', 'device_name' => 'Navigateur pilote']);
identityCheck($response['status'] === 'authenticated' && preg_match('/^[a-f0-9]{64}$/D', $response['device_token']) === 1,
    'Authentication service issues a legacy-compatible device token.');

$token = $response['device_token'];
$authenticator = new LegacyMmwDeviceAuthenticator($pdo, 'site-native', $secret);
$request = new Request('GET', '/v1/config', [
    'x-lepotager-device-token' => $token,
    'x-lepotager-protocol' => '1',
], '');
$actor = $authenticator->authenticate($request);
identityCheck($actor->siteId === 'site-native' && $actor->actorId === $actorId,
    'Legacy device token authenticates against Dendrila membership.');
$lastSeen = $pdo->query(
    "SELECT last_seen_at FROM mmw_devices WHERE actor_id = 'legacy-owner' ORDER BY created_at DESC LIMIT 1"
)->fetchColumn();
identityCheck(is_string($lastSeen) && $lastSeen !== '', 'Legacy device last-seen timestamp is updated.');

$pdo->prepare('UPDATE mmw_devices SET revoked_at = UTC_TIMESTAMP() WHERE actor_id = ?')->execute([$actorId]);
identityExpect(Unauthorized::class, fn () => $authenticator->authenticate($request));

$runtime = IdentityRuntimeFactory::fromEnvironment($pdo, static fn (string $key): ?string => match ($key) {
    'DENDRILA_IDENTITY_PROVIDER' => 'legacy_mmw_pairing',
    'DENDRILA_IDENTITY_SITE_ID' => 'site-native',
    'DENDRILA_IDENTITY_HMAC_SECRET' => $secret,
    'DENDRILA_IDENTITY_ROLE_MAP' => '{"owner":"owner"}',
    default => null,
});
identityCheck($runtime->authenticator instanceof LegacyMmwDeviceAuthenticator
    && $runtime->authentication instanceof AuthenticationService,
    'Configured runtime enables the legacy pairing bridge.');

$defaultRuntime = IdentityRuntimeFactory::fromEnvironment($pdo, static fn (string $key): ?string => null);
identityCheck($defaultRuntime->authenticator instanceof PdoDeviceAuthenticator
    && $defaultRuntime->authentication === null,
    'Unconfigured runtime preserves the fail-closed generic identity behavior.');

$pdo->prepare('DELETE FROM mmw_devices WHERE actor_id = ?')->execute([$actorId]);
$pdo->prepare('DELETE FROM mmw_pair_codes WHERE actor_id = ?')->execute([$actorId]);
$pdo->prepare('DELETE FROM memberships WHERE site_id = ? AND actor_id = ?')->execute(['site-native', $actorId]);
$pdo->prepare('DELETE FROM site_admins WHERE id = ?')->execute([$actorId]);
$pdo->prepare('DELETE FROM auth_rate_limits WHERE site_id = ?')->execute(['site-native']);

echo "Legacy pilot identity, pairing, device revocation and runtime contracts passed.\n";
