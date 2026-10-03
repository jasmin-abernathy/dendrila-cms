<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
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

$inspector = new PrivacyInspector();
$findings = $inspector->inspect([[
    'id' => 'home',
    'url' => 'https://native.test/',
    'content' => '<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>'
        . '<iframe src="https://www.youtube-nocookie.com/embed/demo"></iframe>'
        . '<script src="https://www.google.com/recaptcha/api.js"></script>',
]]);
$editor = new ActorContext('site-native', 'editor-native', 'device-editor');
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
privacyPdoCheck($auditRows === 2, 'Settings and scan mutations append shared CMS audit events.');

$migrations = $pdo->query('SELECT migration_id FROM schema_migrations ORDER BY migration_id')->fetchAll(PDO::FETCH_COLUMN);
privacyPdoCheck(
    $migrations === ['001_core.sql', '002_api_devices_and_settings.sql', '003_privacy_core.sql'],
    'Migration runner records privacy migration exactly once.',
);

echo "Native privacy PDO integration tests passed.\n";
