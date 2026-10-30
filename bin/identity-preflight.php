<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

function requiredEnv(string $key): string
{
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        throw new RuntimeException("Missing environment variable: {$key}");
    }
    return (string) $value;
}

$provider = requiredEnv('DENDRILA_IDENTITY_PROVIDER');
if ($provider !== 'legacy_mmw_pairing') {
    throw new RuntimeException('This preflight currently supports DENDRILA_IDENTITY_PROVIDER=legacy_mmw_pairing only.');
}

$dsn = requiredEnv('DB_DSN');
$username = requiredEnv('DB_USERNAME');
$password = getenv('DB_PASSWORD');
$siteId = requiredEnv('DENDRILA_IDENTITY_SITE_ID');
$secret = requiredEnv('DENDRILA_IDENTITY_HMAC_SECRET');
$roleMapJson = requiredEnv('DENDRILA_IDENTITY_ROLE_MAP');

try {
    $roleMap = json_decode($roleMapJson, true, 16, JSON_THROW_ON_ERROR);
} catch (JsonException $error) {
    throw new RuntimeException('DENDRILA_IDENTITY_ROLE_MAP must be valid JSON.', previous: $error);
}
if (!is_array($roleMap) || array_is_list($roleMap) || $roleMap === []) {
    throw new RuntimeException('DENDRILA_IDENTITY_ROLE_MAP must be a non-empty JSON object.');
}
if (strlen($secret) < 32) {
    throw new RuntimeException('DENDRILA_IDENTITY_HMAC_SECRET must contain at least 32 characters.');
}

$pdo = new PDO($dsn, $username, $password === false ? '' : $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
$requiredTables = [
    'sites', 'roles', 'memberships', 'auth_rate_limits',
    'site_admins', 'mmw_pair_codes', 'mmw_devices',
];
$tableQuery = $pdo->prepare(
    'SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name'
);
foreach ($requiredTables as $table) {
    $tableQuery->execute(['schema' => $database, 'table_name' => $table]);
    if ($tableQuery->fetchColumn() === false) {
        throw new RuntimeException("Required table is missing: {$table}");
    }
}

$admins = $pdo->query(
    'SELECT id, username, role
     FROM site_admins
     ORDER BY created_at ASC, id ASC'
)->fetchAll();
if ($admins === []) {
    throw new RuntimeException('No legacy administrator account exists.');
}

fwrite(STDOUT, "Identity bridge preflight\n");
fwrite(STDOUT, "Database: {$database}\n");
fwrite(STDOUT, "Target site: {$siteId}\n");
fwrite(STDOUT, "Legacy administrators:\n");
foreach ($admins as $admin) {
    $sourceRole = (string) $admin['role'];
    $targetRole = $roleMap[$sourceRole] ?? null;
    $mapping = is_string($targetRole) ? $targetRole : '(not mapped)';
    fwrite(STDOUT, "  - actor_id={$admin['id']} username={$admin['username']} role={$sourceRole} -> {$mapping}\n");
}

$site = $pdo->prepare('SELECT display_name FROM sites WHERE site_id = :site_id LIMIT 1');
$site->execute(['site_id' => $siteId]);
$displayName = $site->fetchColumn();
if ($displayName === false) {
    fwrite(STDOUT, "\nDendrila site is not bootstrapped yet.\n");
    fwrite(STDOUT, "Use one mapped actor_id above with:\n");
    fwrite(STDOUT, "  php bin/bootstrap-site.php {$siteId} \"DISPLAY NAME\" ACTOR_ID false\n");
    exit(3);
}

foreach ($roleMap as $sourceRole => $targetRole) {
    $role = $pdo->prepare(
        'SELECT 1 FROM roles WHERE site_id = :site_id AND role_id = :role_id LIMIT 1'
    );
    $role->execute(['site_id' => $siteId, 'role_id' => $targetRole]);
    if ($role->fetchColumn() === false) {
        throw new RuntimeException("Mapped Dendrila role is missing for {$sourceRole}: {$targetRole}");
    }
}

$activePairCodes = (int) $pdo->query(
    'SELECT COUNT(*) FROM mmw_pair_codes
     WHERE consumed_at IS NULL AND expires_at > UTC_TIMESTAMP()'
)->fetchColumn();
$activeDevices = (int) $pdo->query(
    'SELECT COUNT(*) FROM mmw_devices WHERE revoked_at IS NULL'
)->fetchColumn();

fwrite(STDOUT, "\nDendrila site: {$displayName}\n");
fwrite(STDOUT, "Active pairing codes: {$activePairCodes}\n");
fwrite(STDOUT, "Active legacy devices: {$activeDevices}\n");
fwrite(STDOUT, "HMAC secret: configured (value not displayed)\n");
fwrite(STDOUT, "Preflight passed.\n");
