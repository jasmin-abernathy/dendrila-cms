<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Privacy\PdoPrivacyRepository;
use Dendrila\Cms\Privacy\PrivacyService;
use Dendrila\Cms\Storage\PdoCmsStore;

function wordpressImportPdoCheck(bool $condition, string $message): void
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

$store = new PdoCmsStore($pdo);
$privacy = new PrivacyService(new PdoPrivacyRepository($pdo), new AuthorizationService($store));
$actor = new ActorContext('site-import', 'owner-import', null, 'import');
$state = $privacy->state($actor);

wordpressImportPdoCheck($state['settings']['consent']['enabled'] === true
    && $state['settings']['consent']['layout'] === 'card'
    && $state['settings']['consent']['retention_days'] === 120
    && $state['settings']['scan']['schedule'] === 'weekly',
    'WordPress import persists native consent and scan settings.');

$profile = $state['settings']['legal_profile'];
wordpressImportPdoCheck($profile['controller_name'] === 'Imported Organisation'
    && $profile['privacy_contact'] === 'privacy@example.test'
    && $profile['wizard_reviewed_once'] === true
    && count($profile['treatments']) === 1
    && count($profile['indirect_services']) === 1,
    'WordPress import persists the native legal profile and repeatable rows.');
wordpressImportPdoCheck(strlen($profile['treatments'][0]['id']) === 32
    && strlen($profile['indirect_services'][0]['id']) === 32,
    'Imported legal rows use native stable ids.');

wordpressImportPdoCheck(is_array($state['latest_scan'])
    && ($state['latest_scan']['mode'] ?? null) === 'full'
    && count($state['findings']) === 3,
    'WordPress current scan is stored as one native completed scan.');

$manifest = $privacy->consentManifest('site-import');
wordpressImportPdoCheck(
    array_column($manifest['services'], 'id') === ['google_analytics', 'youtube'],
    'Imported consent manifest preserves optional analytics and blocked external media but excludes reCAPTCHA.',
);

$revision = (int) $pdo->query(
    "SELECT data_revision FROM sites WHERE site_id = 'site-import'"
)->fetchColumn();
wordpressImportPdoCheck($revision === 2,
    'Replaying the same import does not advance the site revision twice.');

$scanCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM privacy_scans WHERE site_id = 'site-import'"
)->fetchColumn();
wordpressImportPdoCheck($scanCount === 1,
    'Replaying the same import does not duplicate the imported scan.');

$idempotencyCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM idempotency_requests WHERE site_id = 'site-import' AND actor_id = 'owner-import'
     AND client_request_id LIKE 'wp-privacy-import-%'"
)->fetchColumn();
wordpressImportPdoCheck($idempotencyCount === 1,
    'WordPress import records one deterministic idempotency request.');

$auditCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM audit_events WHERE site_id = 'site-import' AND entity_type = 'privacy'"
)->fetchColumn();
wordpressImportPdoCheck($auditCount === 2,
    'Imported settings and imported scan each append one native privacy audit event.');

echo "WordPress privacy PDO import tests passed.\n";
