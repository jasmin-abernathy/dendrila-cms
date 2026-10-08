<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$siteId = $argv[1] ?? '';
if ($siteId === '') throw new InvalidArgumentException('Usage: php bin/enable-media-library.php SITE_ID');
$pdo = new PDO(getenv('DB_DSN') ?: '', getenv('DB_USERNAME') ?: '', getenv('DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (Dendrila\Cms\Media\MediaFactory::fromEnvironment($pdo) === null) throw new RuntimeException('Configure private media storage first.');
$pdo->beginTransaction();
try {
    $q = $pdo->prepare('SELECT modules_json FROM site_api_settings WHERE site_id = ? FOR UPDATE');
    $q->execute([$siteId]);
    $json = $q->fetchColumn();
    if (!is_string($json)) throw new RuntimeException('Unknown site.');
    $modules = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    Dendrila\Cms\Api\PdoSiteReadModel::safeModules($modules);
    foreach ($modules as $module) {
        if ($module['id'] === 'media_library') {
            if ($module['kind'] !== 'media_library') throw new RuntimeException('Module ID already in use.');
            $pdo->commit(); fwrite(STDOUT, "Médiathèque déjà activée.\n"); exit;
        }
    }
    $modules[] = Dendrila\Cms\Api\MediaLibraryModule::definition();
    $q = $pdo->prepare('UPDATE site_api_settings SET modules_json = ?, updated_at = UTC_TIMESTAMP(6) WHERE site_id = ?');
    $q->execute([json_encode($modules, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $siteId]);
    $q = $pdo->prepare('UPDATE sites SET config_version = config_version + 1, data_revision = data_revision + 1 WHERE site_id = ?');
    $q->execute([$siteId]);
    $pdo->commit();
    fwrite(STDOUT, "Médiathèque activée.\n");
} catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
