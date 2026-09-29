<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php bin/bootstrap-site.php <site_id> <display_name> <owner_actor_id> [review_before_publish=true|false]\n");
    exit(2);
}

[$siteId, $displayName, $ownerActorId] = array_slice($argv, 1, 3);
$reviewBeforePublish = ($argv[4] ?? 'true') === 'true';
if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $siteId) || trim($displayName) === '' || trim($ownerActorId) === '') {
    fwrite(STDERR, "Invalid site id, name, or owner actor id.\n");
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
$root = dirname(__DIR__);
$roles = json_decode(file_get_contents($root . '/database/seeders/default-roles.json'), true, 512, JSON_THROW_ON_ERROR);
$pageType = json_decode(file_get_contents($root . '/examples/content-types/page.json'), true, 512, JSON_THROW_ON_ERROR);
$now = gmdate('Y-m-d H:i:s');

$pdo->beginTransaction();
try {
    $site = $pdo->prepare('INSERT INTO sites (site_id, display_name, config_version, data_revision, policy_json, created_at, updated_at)
        VALUES (:site_id, :display_name, 1, 1, :policy_json, :created_at, :updated_at)');
    $site->execute([
        'site_id' => $siteId, 'display_name' => $displayName,
        'policy_json' => json_encode(['review_before_publish' => $reviewBeforePublish, 'allow_offline_queue' => false], JSON_THROW_ON_ERROR),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $apiSettings = $pdo->prepare(
        'INSERT INTO site_api_settings (site_id, branding_json, modules_json, created_at, updated_at)
         VALUES (:site_id, :branding_json, :modules_json, :created_at, :updated_at)'
    );
    $apiSettings->execute([
        'site_id' => $siteId, 'branding_json' => '{}', 'modules_json' => '[]',
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $insertRole = $pdo->prepare('INSERT INTO roles (site_id, role_id, label) VALUES (:site_id, :role_id, :label)');
    $insertPermission = $pdo->prepare('INSERT INTO role_permissions (site_id, role_id, permission) VALUES (:site_id, :role_id, :permission)');
    foreach ($roles as $role) {
        $insertRole->execute(['site_id' => $siteId, 'role_id' => $role['id'], 'label' => $role['label']]);
        foreach ($role['permissions'] as $permission) {
            $insertPermission->execute(['site_id' => $siteId, 'role_id' => $role['id'], 'permission' => $permission]);
        }
    }
    $membership = $pdo->prepare('INSERT INTO memberships (site_id, actor_id, role_id, created_at) VALUES (:site_id, :actor_id, \'owner\', :created_at)');
    $membership->execute(['site_id' => $siteId, 'actor_id' => $ownerActorId, 'created_at' => $now]);
    $type = $pdo->prepare('INSERT INTO content_types (site_id, type_id, definition_json, enabled, created_at, updated_at)
        VALUES (:site_id, :type_id, :definition_json, 1, :created_at, :updated_at)');
    $type->execute([
        'site_id' => $siteId, 'type_id' => $pageType['id'],
        'definition_json' => json_encode($pageType, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_at' => $now, 'updated_at' => $now,
    ]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

fwrite(STDOUT, "Created site {$siteId} and owner membership for actor {$ownerActorId}.\n");
