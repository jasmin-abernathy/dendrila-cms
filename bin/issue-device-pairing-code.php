<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Api\PdoPairingCodeStore;
use LePotager\Cms\Api\PdoPairingIdentityProvider;

if ($argc < 3 || $argc > 4) {
    fwrite(STDERR, "Usage: php bin/issue-device-pairing-code.php <site_id> <actor_id> [ttl_seconds]\n");
    exit(2);
}

[$siteId, $actorId] = array_slice($argv, 1, 2);
$ttl = isset($argv[3]) ? filter_var($argv[3], FILTER_VALIDATE_INT) : 600;
if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $siteId)
    || trim($actorId) === '' || strlen($actorId) > 128
    || $ttl === false || $ttl < 60 || $ttl > 900) {
    fwrite(STDERR, "Invalid site id, actor id, or TTL (allowed range: 60–900 seconds).\n");
    exit(2);
}

$dsn = getenv('DB_DSN') ?: '';
$username = getenv('DB_USERNAME') ?: '';
if ($dsn === '' || $username === '') {
    fwrite(STDERR, "DB_DSN and DB_USERNAME are required.\n");
    exit(2);
}

$pdo = new PDO($dsn, $username, getenv('DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$identity = new PdoPairingIdentityProvider(new PdoPairingCodeStore($pdo));
$code = str_pad((string) random_int(0, 999_999_999_999), 12, '0', STR_PAD_LEFT);
$expiresAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->modify("+{$ttl} seconds");
$identity->issuePairingCode($siteId, $actorId, $code, $expiresAt);

fwrite(STDOUT, "Temporary pairing code (valid {$ttl} seconds, shown once): {$code}\n");
fwrite(STDOUT, "Enter it in the RésoSoin practitioner app. Do not send it by email or include it in a URL.\n");
