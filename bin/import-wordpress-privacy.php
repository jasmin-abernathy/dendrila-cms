#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Privacy\PdoPrivacyRepository;
use Dendrila\Cms\Privacy\PrivacyService;
use Dendrila\Cms\Privacy\WordPressPrivacyImport;
use Dendrila\Cms\Storage\PdoCmsStore;

$siteId = $argv[1] ?? '';
$actorId = $argv[2] ?? '';
$inputPath = $argv[3] ?? '';
if (!is_string($siteId) || $siteId === '' || !is_string($actorId) || $actorId === ''
    || !is_string($inputPath) || $inputPath === '') {
    fwrite(STDERR, "Usage: php bin/import-wordpress-privacy.php <site_id> <actor_id> <export.json>\n");
    exit(2);
}

$realPath = realpath($inputPath);
if ($realPath === false || !is_file($realPath) || !is_readable($realPath)) {
    fwrite(STDERR, "Import file is not readable.\n");
    exit(2);
}
$size = filesize($realPath);
if ($size === false || $size > 5_000_000) {
    fwrite(STDERR, "Import file must be 5 MB or smaller.\n");
    exit(2);
}

$dsn = getenv('DB_DSN') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
if ($dsn === '' || $username === '') {
    fwrite(STDERR, "DB_DSN and DB_USERNAME are required.\n");
    exit(2);
}

try {
    $raw = (string) file_get_contents($realPath);
    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($payload) || ($payload !== [] && array_is_list($payload))) {
        throw new InvalidArgumentException('Import payload must be a JSON object.');
    }

    $mapped = (new WordPressPrivacyImport())->map($payload);
    if ($mapped['settings_changes'] === [] && $mapped['findings'] === []) {
        throw new InvalidArgumentException('Import contains no supported Dendrila Privacy data.');
    }

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $store = new PdoCmsStore($pdo);
    $authorization = new AuthorizationService($store);
    $privacy = new PrivacyService(new PdoPrivacyRepository($pdo), $authorization);
    $actor = new ActorContext($siteId, $actorId, null, 'import');

    $canonical = json_encode(
        ['site_id' => $siteId, 'payload' => $payload],
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    );
    $requestHash = hash('sha256', $canonical);
    $requestId = 'wp-privacy-import-' . substr($requestHash, 0, 32);

    $pdo->beginTransaction();
    try {
        $existing = $store->findIdempotency($siteId, $actorId, $requestId);
        if ($existing !== null) {
            if (!hash_equals($existing['request_hash'], $requestHash)
                || !is_array($existing['response'] ?? null)) {
                throw new RuntimeException('Import idempotency record is inconsistent.');
            }
            $pdo->commit();
            $response = $existing['response'];
            $response['replayed'] = true;
            echo json_encode(
                $response,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            ) . "\n";
            exit(0);
        }

        if (!$store->claimIdempotency($siteId, $actorId, $requestId, $requestHash)) {
            throw new RuntimeException('Could not claim the import idempotency key.');
        }

        if ($mapped['settings_changes'] !== []) {
            $privacy->updateSettings($actor, $mapped['settings_changes']);
        }

        $scan = null;
        if ($mapped['findings'] !== []) {
            $scan = $privacy->recordCompletedScan($actor, $mapped['scan_mode'], $mapped['findings']);
        }

        $siteRevision = $store->incrementDataRevision($siteId);
        $response = [
            'status' => 'imported',
            'request_id' => $requestId,
            'replayed' => false,
            'site_id' => $siteId,
            'site_revision' => $siteRevision,
            'settings_imported' => $mapped['settings_changes'] !== [],
            'findings_imported' => count($mapped['findings']),
            'scan_id' => is_array($scan) ? ($scan['scan']['scan_id'] ?? null) : null,
            'source_meta' => $mapped['source_meta'],
            'warnings' => $mapped['warnings'],
        ];
        $store->completeIdempotency(
            $siteId,
            $actorId,
            $requestId,
            json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
        $pdo->commit();

        echo json_encode(
            $response,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ) . "\n";
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $error;
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
