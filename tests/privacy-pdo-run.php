<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Api\PdoSiteChangeService;
use Dendrila\Cms\Api\PdoSiteReadModel;
use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\IdempotencyConflict;
use Dendrila\Cms\Privacy\PdoPrivacyRepository;
use Dendrila\Cms\Privacy\PrivacyInspector;
use Dendrila\Cms\Privacy\PrivacyService;
use Dendrila\Cms\Storage\PdoCmsStore;

function privacyPdoCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function privacyPdoExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        privacyPdoCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
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

$legacySettings = $pdo->query(
    "SELECT settings_json FROM privacy_settings WHERE site_id = 'legacy-site'"
)->fetchColumn();
privacyPdoCheck(is_string($legacySettings), 'Migration 003 backfills privacy settings for an existing site.');
$legacyDecoded = json_decode($legacySettings, true, 64, JSON_THROW_ON_ERROR);
privacyPdoCheck(($legacyDecoded['consent']['enabled'] ?? null) === false, 'Legacy consent is disabled by default.');
privacyPdoCheck(($legacyDecoded['scan']['schedule'] ?? null) === 'off', 'Legacy scheduled scans are disabled by default.');

$permission = $pdo->prepare(
    'SELECT permission FROM role_permissions WHERE site_id = :site_id AND role_id = :role_id
     AND permission LIKE \'privacy.%\' ORDER BY permission'
);
$expectedPermissions = [
    'owner' => ['privacy.manage', 'privacy.read', 'privacy.scan'],
    'admin' => ['privacy.manage', 'privacy.read', 'privacy.scan'],
    'editor' => ['privacy.read', 'privacy.scan'],
    'contributor' => [],
];
foreach ($expectedPermissions as $roleId => $expected) {
    $permission->execute(['site_id' => 'legacy-site', 'role_id' => $roleId]);
    privacyPdoCheck(
        $permission->fetchAll(PDO::FETCH_COLUMN) === $expected,
        "Migration 003 backfills the expected Privacy permissions for {$roleId}.",
    );
}

$newSettings = $pdo->query(
    "SELECT settings_json FROM privacy_settings WHERE site_id = 'site-native'"
)->fetchColumn();
privacyPdoCheck(is_string($newSettings), 'Site bootstrap creates native privacy settings for new sites.');

$store = new PdoCmsStore($pdo);
$service = new PrivacyService(new PdoPrivacyRepository($pdo), new AuthorizationService($store));
$owner = new ActorContext('site-native', 'owner-native', 'device-owner');
$settings = $service->updateSettings($owner, [
    'consent' => ['enabled' => true, 'layout' => 'card', 'retention_days' => 120],
    'legal_profile' => ['controller_name' => 'Native Site'],
]);
privacyPdoCheck($settings['consent']['enabled'] === true, 'Owner can manage native privacy settings through PDO.');

$membership = $pdo->prepare(
    'INSERT INTO memberships (site_id, actor_id, role_id, created_at)
     VALUES (:site_id, :actor_id, :role_id, UTC_TIMESTAMP(6))'
);
$membership->execute([
    'site_id' => 'site-native',
    'actor_id' => 'editor-native',
    'role_id' => 'editor',
]);


$readModel = new PdoSiteReadModel($pdo);
$ownerConfig = $readModel->configuration($owner);
$ownerPrivacyModules = array_values(array_filter(
    $ownerConfig['modules'],
    static fn (array $module): bool => ($module['id'] ?? null) === 'privacy',
));
privacyPdoCheck(count($ownerPrivacyModules) === 1
    && $ownerPrivacyModules[0]['kind'] === 'settings'
    && $ownerPrivacyModules[0]['writable'] === true,
    'Owner receives one writable native privacy settings module through MMW v1.');

$editor = new ActorContext('site-native', 'editor-native', 'device-editor');
$editorConfig = $readModel->configuration($editor);
$editorPrivacyModules = array_values(array_filter(
    $editorConfig['modules'],
    static fn (array $module): bool => ($module['id'] ?? null) === 'privacy',
));
privacyPdoCheck(count($editorPrivacyModules) === 1 && $editorPrivacyModules[0]['writable'] === false,
    'Editor can read the privacy settings module but cannot manage it.');

$membership->execute([
    'site_id' => 'site-native',
    'actor_id' => 'contributor-native',
    'role_id' => 'contributor',
]);
$contributorConfig = $readModel->configuration(new ActorContext('site-native', 'contributor-native'));
privacyPdoCheck(
    count(array_filter($contributorConfig['modules'],
        static fn (array $module): bool => ($module['id'] ?? null) === 'privacy')) === 0,
    'Roles without privacy.read do not receive the native privacy module.',
);

$ownerSnapshot = $readModel->snapshot($owner);
$ownerData = (array) $ownerSnapshot['data'];
privacyPdoCheck(($ownerData['privacy']['consent_enabled'] ?? null) === true
    && ($ownerData['privacy']['consent_layout'] ?? null) === 'card'
    && ($ownerData['privacy']['consent_retention_days'] ?? null) === 120,
    'MMW snapshot projects the current privacy settings through existing settings primitives.');

$beforeRevision = (int) $pdo->query(
    "SELECT data_revision FROM sites WHERE site_id = 'site-native'"
)->fetchColumn();
$changeService = new PdoSiteChangeService($pdo);
$privacyChangeRequest = [
    'module_id' => 'privacy',
    'action' => 'update_fields',
    'client_request_id' => 'privacy-0001',
    'payload' => (object) [
        'consent_enabled' => false,
        'consent_layout' => 'bar',
        'consent_retention_days' => 180,
        'consent_footer_link' => false,
        'scan_schedule' => 'daily',
    ],
];
$privacyChange = $changeService->apply($owner, $privacyChangeRequest);
privacyPdoCheck($privacyChange['status'] === 'applied'
    && $privacyChange['result']['values']['consent_enabled'] === false
    && $privacyChange['result']['values']['scan_schedule'] === 'daily',
    'Owner can update native privacy settings through the existing MMW changes route.');

$afterRevision = (int) $pdo->query(
    "SELECT data_revision FROM sites WHERE site_id = 'site-native'"
)->fetchColumn();
privacyPdoCheck($afterRevision === $beforeRevision + 1,
    'An MMW privacy settings change advances the site snapshot revision exactly once.');

$replayedPrivacyChange = $changeService->apply($owner, $privacyChangeRequest);
$afterReplayRevision = (int) $pdo->query(
    "SELECT data_revision FROM sites WHERE site_id = 'site-native'"
)->fetchColumn();
privacyPdoCheck($replayedPrivacyChange === $privacyChange && $afterReplayRevision === $afterRevision,
    'Repeated privacy changes replay idempotently without a second revision or mutation.');

$conflictingRequest = $privacyChangeRequest;
$conflictingRequest['payload'] = (object) ['consent_enabled' => true];
privacyPdoExpect(IdempotencyConflict::class, fn () => $changeService->apply($owner, $conflictingRequest));

privacyPdoExpect(AccessDenied::class, fn () => $changeService->apply($editor, [
    'module_id' => 'privacy',
    'action' => 'update_fields',
    'client_request_id' => 'privacy-editor-1',
    'payload' => (object) ['consent_enabled' => true],
]));
$afterDenied = $service->state($owner);
privacyPdoCheck($afterDenied['settings']['consent']['enabled'] === false,
    'Read-only privacy roles cannot mutate settings through MMW.');

$updatedSnapshot = $readModel->snapshot($owner);
$updatedData = (array) $updatedSnapshot['data'];
privacyPdoCheck($updatedSnapshot['revision'] === $afterRevision
    && $updatedData['privacy']['consent_enabled'] === false
    && $updatedData['privacy']['scan_schedule'] === 'daily',
    'MMW snapshot exposes the updated privacy values at the new site revision.');

$setModules = $pdo->prepare(
    'UPDATE site_api_settings SET modules_json = :modules_json WHERE site_id = :site_id'
);
$setModules->execute([
    'site_id' => 'site-native',
    'modules_json' => '[{"id":"privacy"}]',
]);
privacyPdoExpect(RuntimeException::class, fn () => $readModel->configuration($owner));
$setModules->execute([
    'site_id' => 'site-native',
    'modules_json' => '[]',
]);

$inspector = new PrivacyInspector();
$findings = $inspector->inspect([[
    'id' => 'home',
    'url' => 'https://native.test/',
    'content' => '<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>'
        . '<iframe src="https://www.youtube-nocookie.com/embed/demo"></iframe>'
        . '<script src="https://www.google.com/recaptcha/api.js"></script>',
]]);
$scan = $service->recordCompletedScan($editor, 'full', $findings);
privacyPdoCheck($scan['scan']['summary']['total'] === 3, 'PDO scan persists all detected findings.');

$manifest = $service->consentManifest('site-native');
privacyPdoCheck(
    array_column($manifest['services'], 'id') === ['google_analytics', 'youtube'],
    'Public manifest exposes only optional detected services.',
);
privacyPdoCheck(
    !array_key_exists('legal_profile', $manifest) && !array_key_exists('evidence', $manifest),
    'Public manifest excludes legal profile and scan evidence.',
);

$legacyState = $service->state(new ActorContext('legacy-site', 'legacy-owner'));
privacyPdoCheck(
    $legacyState['latest_scan'] === null && $legacyState['settings']['consent']['enabled'] === false,
    'Legacy site remains isolated from the new site privacy state.',
);

$scanRows = (int) $pdo->query(
    "SELECT COUNT(*) FROM privacy_scans WHERE site_id = 'site-native'"
)->fetchColumn();
$findingRows = (int) $pdo->query(
    "SELECT COUNT(*) FROM privacy_findings WHERE site_id = 'site-native'"
)->fetchColumn();
$auditRows = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_events WHERE site_id = 'site-native' AND entity_type = 'privacy'"
)->fetchColumn();
privacyPdoCheck($scanRows === 1 && $findingRows === 3, 'Privacy scans and findings persist with site isolation.');
privacyPdoCheck($auditRows === 4,
    'Direct settings, scan, MMW settings and denied MMW mutations append shared CMS audit events.');

$migrations = $pdo->query('SELECT migration_id FROM schema_migrations ORDER BY migration_id')->fetchAll(PDO::FETCH_COLUMN);
privacyPdoCheck(
    $migrations === ['001_core.sql', '002_api_devices_and_settings.sql', '003_privacy_core.sql'],
    'Migration runner records privacy migration exactly once.',
);

echo "Native privacy PDO integration tests passed.\n";
