<?php
declare(strict_types=1);

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php bin/issue-device-token.php <site_id> <actor_id> <device_name>\n");
    exit(2);
}

[$siteId, $actorId, $deviceName] = array_slice($argv, 1);
if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $siteId)
    || trim($actorId) === '' || strlen($actorId) > 128
    || trim($deviceName) === '' || strlen($deviceName) > 120) {
    fwrite(STDERR, "Invalid site id, actor id, or device name.\n");
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
$membership = $pdo->prepare(
    'SELECT 1 FROM memberships WHERE site_id = :site_id AND actor_id = :actor_id LIMIT 1'
);
$membership->execute(['site_id' => $siteId, 'actor_id' => $actorId]);
if ($membership->fetchColumn() === false) {
    fwrite(STDERR, "The actor is not a member of this site.\n");
    exit(2);
}

$deviceId = bin2hex(random_bytes(16));
$token = bin2hex(random_bytes(32));
$insert = $pdo->prepare(
    'INSERT INTO devices (site_id, device_id, actor_id, device_name, token_hash, created_at)
     VALUES (:site_id, :device_id, :actor_id, :device_name, :token_hash, UTC_TIMESTAMP(6))'
);
$insert->execute([
    'site_id' => $siteId,
    'device_id' => $deviceId,
    'actor_id' => $actorId,
    'device_name' => $deviceName,
    'token_hash' => hash('sha256', $token),
]);

fwrite(STDOUT, "Device: {$deviceId}\n");
fwrite(STDOUT, "Copy this token now; it cannot be displayed again:\n{$token}\n");
fwrite(STDOUT, "This CLI bootstrap is for development only. Use protocol auth/pairing before production.\n");
