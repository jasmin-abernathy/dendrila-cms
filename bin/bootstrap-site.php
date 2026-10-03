<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Api\PdoSiteReadModel;
use LePotager\Cms\Content\ContentTypeDefinition;

if ($argc < 4 || $argc > 6) {
    fwrite(STDERR, "Usage: php bin/bootstrap-site.php <site_id> <display_name> <owner_actor_id> [review_before_publish=true|false] [kit_id]\n");
    exit(2);
}

[$siteId, $displayName, $ownerActorId] = array_slice($argv, 1, 3);
$reviewBeforePublish = ($argv[4] ?? 'true') === 'true';
$kitId = $argv[5] ?? null;
if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $siteId)
    || trim($displayName) === '' || trim($ownerActorId) === '') {
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
$types = [json_decode(file_get_contents($root . '/examples/content-types/page.json'), true, 512, JSON_THROW_ON_ERROR)];
$apiModules = [];

if ($kitId !== null) {
    if (!preg_match('/^[a-z][a-z0-9_-]{1,63}$/', $kitId)) {
        fwrite(STDERR, "Invalid kit id.\n");
        exit(2);
    }
    $kitPath = $root . '/kits/' . $kitId . '/kit.json';
    if (!is_file($kitPath)) {
        fwrite(STDERR, "The requested kit does not exist.\n");
        exit(2);
    }
    $kit = json_decode(file_get_contents($kitPath), true, 512, JSON_THROW_ON_ERROR);
    $contentTypeIds = $kit['content_types'] ?? null;
    if (!is_array($contentTypeIds) || !array_is_list($contentTypeIds)) {
        throw new RuntimeException('The kit content_types value must be a list.');
    }
    foreach ($contentTypeIds as $typeId) {
        if (!is_string($typeId) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $typeId)) {
            throw new RuntimeException('The kit contains an invalid content type id.');
        }
        if ($typeId === 'page') {
            continue;
        }
        $typePath = $root . '/kits/' . $kitId . '/content-types/' . $typeId . '.json';
        if (!is_file($typePath)) {
            throw new RuntimeException("Content type definition is missing: {$typeId}.");
        }
        $definition = json_decode(file_get_contents($typePath), true, 512, JSON_THROW_ON_ERROR);
        if ((new ContentTypeDefinition($definition))->id() !== $typeId) {
            throw new RuntimeException("Content type definition id does not match: {$typeId}.");
        }
        $types[] = $definition;
    }
    $apiModules = $kit['api_modules'] ?? [];
    if (!is_array($apiModules) || !array_is_list($apiModules)) {
        throw new RuntimeException('The kit api_modules value must be a list.');
    }
    PdoSiteReadModel::safeModules($apiModules);
}

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
        'site_id' => $siteId, 'branding_json' => '{}',
        'modules_json' => json_encode($apiModules, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
    foreach ($types as $definition) {
        $type->execute([
            'site_id' => $siteId, 'type_id' => $definition['id'],
            'definition_json' => json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

$kitMessage = $kitId === null ? '' : " with kit {$kitId}";
fwrite(STDOUT, "Created site {$siteId}{$kitMessage} and owner membership for actor {$ownerActorId}.\n");
