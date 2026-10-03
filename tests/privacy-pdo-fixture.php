<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$dsn = getenv('DB_DSN') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
if ($dsn === '' || $username === '') {
    fwrite(STDERR, "DB_DSN and DB_USERNAME are required.\n");
    exit(2);
}

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

function applyFixtureSql(PDO $pdo, string $file): void
{
    $delimiter = ';';
    $buffer = '';
    foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $match)) {
            $delimiter = $match[1];
            continue;
        }
        $buffer .= $line . "\n";
        if (str_ends_with(rtrim($line), $delimiter)) {
            $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
            $buffer = '';
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }
    }
    if (trim($buffer) !== '') {
        throw new RuntimeException('Unterminated SQL fixture statement in ' . basename($file));
    }
}

applyFixtureSql($pdo, $root . '/database/migrations/001_core.sql');
applyFixtureSql($pdo, $root . '/database/migrations/002_api_devices_and_settings.sql');

$pdo->exec('CREATE TABLE schema_migrations (
    migration_id VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
$recordMigration = $pdo->prepare(
    'INSERT INTO schema_migrations (migration_id, applied_at) VALUES (:migration_id, UTC_TIMESTAMP(6))'
);
foreach (['001_core.sql', '002_api_devices_and_settings.sql'] as $migrationId) {
    $recordMigration->execute(['migration_id' => $migrationId]);
}

$now = gmdate('Y-m-d H:i:s');
$site = $pdo->prepare(
    'INSERT INTO sites (site_id, display_name, config_version, data_revision, policy_json, created_at, updated_at)
     VALUES (:site_id, :display_name, 1, 1, :policy_json, :created_at, :updated_at)'
);
$site->execute([
    'site_id' => 'legacy-site',
    'display_name' => 'Legacy privacy site',
    'policy_json' => '{"review_before_publish":false,"allow_offline_queue":false}',
    'created_at' => $now,
    'updated_at' => $now,
]);
$api = $pdo->prepare(
    'INSERT INTO site_api_settings (site_id, branding_json, modules_json, created_at, updated_at)
     VALUES (:site_id, :branding_json, :modules_json, :created_at, :updated_at)'
);
$api->execute([
    'site_id' => 'legacy-site',
    'branding_json' => '{}',
    'modules_json' => '[]',
    'created_at' => $now,
    'updated_at' => $now,
]);

$insertRole = $pdo->prepare(
    'INSERT INTO roles (site_id, role_id, label) VALUES (:site_id, :role_id, :label)'
);
foreach ([
    'owner' => 'Propriétaire',
    'admin' => 'Administrateur',
    'editor' => 'Éditeur',
    'contributor' => 'Contributeur',
] as $roleId => $label) {
    $insertRole->execute(['site_id' => 'legacy-site', 'role_id' => $roleId, 'label' => $label]);
}
$membership = $pdo->prepare(
    'INSERT INTO memberships (site_id, actor_id, role_id, created_at)
     VALUES (:site_id, :actor_id, :role_id, :created_at)'
);
$membership->execute([
    'site_id' => 'legacy-site',
    'actor_id' => 'legacy-owner',
    'role_id' => 'owner',
    'created_at' => $now,
]);

fwrite(STDOUT, "Prepared legacy CMS schema at migration 002.\n");
