<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Api\PdoSiteChangeService;
use Dendrila\Cms\Api\PdoSiteReadModel;
use Dendrila\Cms\Api\PrivacyLegalModules;
use Dendrila\Cms\Api\PrivacyDocumentsModule;
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

$ownerModuleIds = array_column($ownerConfig['modules'], 'id');
foreach (PrivacyLegalModules::ids() as $nativeLegalId) {
    privacyPdoCheck(in_array($nativeLegalId, $ownerModuleIds, true),
        "Owner receives native legal module {$nativeLegalId}.");
}
privacyPdoCheck(in_array(PrivacyDocumentsModule::ID, $ownerModuleIds, true),
    'Owner receives the native public privacy documents module.');

$editor = new ActorContext('site-native', 'editor-native', 'device-editor');
$editorConfig = $readModel->configuration($editor);
$editorPrivacyModules = array_values(array_filter(
    $editorConfig['modules'],
    static fn (array $module): bool => ($module['id'] ?? null) === 'privacy',
));
privacyPdoCheck(count($editorPrivacyModules) === 1 && $editorPrivacyModules[0]['writable'] === false,
    'Editor can read the privacy settings module but cannot manage it.');

foreach ($editorConfig['modules'] as $module) {
    $moduleId = (string) ($module['id'] ?? '');
    if (PrivacyLegalModules::handles($moduleId) || $moduleId === PrivacyDocumentsModule::ID) {
        privacyPdoCheck(($module['writable'] ?? null) === false,
            'Editor receives legal documentation modules as read-only.');
    }
}

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


$legalChange = $changeService->apply($owner, [
    'module_id' => PrivacyLegalModules::LEGAL_ID,
    'action' => 'update_fields',
    'client_request_id' => 'privacy-legal-1',
    'payload' => (object) [
        'privacy_contact' => 'privacy@native.test',
        'collection_mode' => 'both',
        'external_booking' => 'yes',
        'external_booking_tools' => 'Native booking',
        'transfers_status' => 'no',
    ],
]);
privacyPdoCheck($legalChange['result']['values']['collection_mode'] === 'both',
    'MMW legal settings update the native legal profile.');

$treatmentCreate = $changeService->apply($owner, [
    'module_id' => PrivacyLegalModules::TREATMENTS_ID,
    'action' => 'create_item',
    'client_request_id' => 'privacy-treat-1',
    'payload' => (object) [
        'purpose' => 'Manage appointments',
        'data_categories' => 'Identity and appointment details',
        'legal_basis' => 'contract',
        'recipients' => 'Care team',
        'retention' => 'Applicable retention period',
        'mandatory' => 'contract',
    ],
]);
$treatmentId = $treatmentCreate['result']['item']['id'] ?? '';
privacyPdoCheck(is_string($treatmentId) && strlen($treatmentId) === 32,
    'MMW creates a treatment with a stable native id.');

$treatmentReplay = $changeService->apply($owner, [
    'module_id' => PrivacyLegalModules::TREATMENTS_ID,
    'action' => 'create_item',
    'client_request_id' => 'privacy-treat-1',
    'payload' => (object) [
        'purpose' => 'Manage appointments',
        'data_categories' => 'Identity and appointment details',
        'legal_basis' => 'contract',
        'recipients' => 'Care team',
        'retention' => 'Applicable retention period',
        'mandatory' => 'contract',
    ],
]);
privacyPdoCheck($treatmentReplay === $treatmentCreate,
    'MMW treatment creation replays idempotently.');

$treatmentUpdate = $changeService->apply($owner, [
    'module_id' => PrivacyLegalModules::TREATMENTS_ID,
    'action' => 'update_item',
    'client_request_id' => 'privacy-treat-2',
    'payload' => (object) [
        'item_id' => $treatmentId,
        'retention' => 'Updated retention period',
    ],
]);
privacyPdoCheck(($treatmentUpdate['result']['item']['id'] ?? null) === $treatmentId
    && ($treatmentUpdate['result']['item']['retention'] ?? null) === 'Updated retention period',
    'MMW updates a treatment without changing its native id.');

$sourceCreate = $changeService->apply($owner, [
    'module_id' => PrivacyLegalModules::SOURCES_ID,
    'action' => 'create_item',
    'client_request_id' => 'privacy-source-1',
    'payload' => (object) [
        'service_id' => 'booking_provider',
        'label' => 'Booking provider',
        'state' => 'active',
        'categories' => 'Name and email',
        'source' => 'Booking form',
        'transfer_status' => 'unknown',
        'automated_decision' => 'no',
    ],
]);
privacyPdoCheck(($sourceCreate['result']['item']['service_id'] ?? null) === 'booking_provider',
    'MMW creates an indirect source in the native legal profile.');

$legalSnapshot = $readModel->snapshot($owner);
$legalData = (array) $legalSnapshot['data'];
privacyPdoCheck(($legalData[PrivacyLegalModules::LEGAL_ID]['privacy_contact'] ?? null) === 'privacy@native.test'
    && count($legalData[PrivacyLegalModules::TREATMENTS_ID]['items'] ?? []) === 1
    && count($legalData[PrivacyLegalModules::SOURCES_ID]['items'] ?? []) === 1,
    'MMW snapshot projects legal settings, treatments and indirect sources.');

privacyPdoExpect(AccessDenied::class, fn () => $changeService->apply($editor, [
    'module_id' => PrivacyLegalModules::LEGAL_ID,
    'action' => 'update_fields',
    'client_request_id' => 'privacy-legal-editor',
    'payload' => (object) ['privacy_contact' => 'forbidden@example.test'],
]));

$deleteTreatment = $changeService->apply($owner, [
    'module_id' => PrivacyLegalModules::TREATMENTS_ID,
    'action' => 'delete_item',
    'client_request_id' => 'privacy-treat-3',
    'payload' => (object) ['item_id' => $treatmentId],
]);
privacyPdoCheck(($deleteTreatment['result']['deleted_item']['id'] ?? null) === $treatmentId,
    'MMW deletes the addressed treatment only.');

$afterLegalSnapshot = $readModel->snapshot($owner);
$afterLegalData = (array) $afterLegalSnapshot['data'];
privacyPdoCheck(($afterLegalData[PrivacyLegalModules::TREATMENTS_ID]['items'] ?? []) === []
    && count($afterLegalData[PrivacyLegalModules::SOURCES_ID]['items'] ?? []) === 1,
    'Deleting a treatment leaves indirect sources intact.');

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

$completeProfile = $service->updateLegalProfile($owner, [
    'controller_address' => "1 rue Exemple\n57000 Metz",
    'public_contact_email' => 'contact@native.test',
    'publication_director' => 'Direction Native',
    'host_name' => 'Hébergeur France',
    'host_address' => '2 rue Serveur, 75000 Paris',
    'host_phone' => '+33 1 00 00 00 00',
    'cookie_nonessential' => 'yes',
    'cookie_consent_status' => 'yes',
    'treatments' => [[
        'purpose' => 'Gérer les rendez-vous',
        'data_categories' => 'Identité et informations de rendez-vous',
        'legal_basis' => 'contract',
        'recipients' => 'Équipe du service',
        'retention' => 'Durée nécessaire au suivi puis archivage applicable',
        'mandatory' => 'contract',
        'consequences' => 'Le rendez-vous ne peut pas être organisé sans ces informations.',
    ]],
]);
privacyPdoCheck($completeProfile['controller_address'] !== '',
    'Legal profile can be completed before explicit document approval.');

$legalPreview = $service->legalDocumentPreview($owner, 'legal_notice');
privacyPdoCheck($legalPreview['readiness']['errors'] === []
    && str_contains($legalPreview['html'], 'Mentions légales'),
    'Legal notice preview is generated only from normalized native data.');

$documentSnapshotBefore = $readModel->snapshot($owner);
$documentItemsBefore = ((array) $documentSnapshotBefore['data'])[PrivacyDocumentsModule::ID]['items'] ?? [];
$noticeBefore = array_values(array_filter(
    $documentItemsBefore,
    static fn (array $item): bool => ($item['id'] ?? null) === 'legal_notice',
))[0] ?? null;
privacyPdoCheck(is_array($noticeBefore) && ($noticeBefore['status'] ?? null) === 'Prêt à valider',
    'MMW exposes a legal notice as ready before human approval.');

$approveViaMmw = $changeService->apply($owner, [
    'module_id' => PrivacyDocumentsModule::ID,
    'action' => 'item_action',
    'client_request_id' => 'privacy-doc-approve-1',
    'payload' => (object) [
        'item_id' => 'legal_notice',
        'action_id' => 'approve',
    ],
]);
privacyPdoCheck(($approveViaMmw['result']['item']['status'] ?? null) === 'Publié',
    'Owner can explicitly approve a ready privacy document through MMW.');

$approveReplay = $changeService->apply($owner, [
    'module_id' => PrivacyDocumentsModule::ID,
    'action' => 'item_action',
    'client_request_id' => 'privacy-doc-approve-1',
    'payload' => (object) [
        'item_id' => 'legal_notice',
        'action_id' => 'approve',
    ],
]);
privacyPdoCheck($approveReplay === $approveViaMmw,
    'Privacy document approval replays idempotently.');

$publicLegal = $service->publicLegalDocument('site-native', 'legal_notice');
privacyPdoCheck(is_array($publicLegal)
    && $publicLegal['html'] === $legalPreview['html']
    && !array_key_exists('approved_by_id', $publicLegal),
    'Only an explicitly approved and current legal document is exposed publicly.');

$privacyPreview = $service->legalDocumentPreview($owner, 'privacy');
$cookiePreview = $service->legalDocumentPreview($owner, 'cookies');
privacyPdoCheck($privacyPreview['readiness']['errors'] === []
    && str_contains($privacyPreview['html'], 'Protection des données personnelles')
    && $cookiePreview['readiness']['errors'] === []
    && str_contains($cookiePreview['html'], 'Services facultatifs détectés')
    && str_contains($cookiePreview['html'], 'YouTube'),
    'Privacy and cookie policies reflect the current consent state and detected optional services.');

$service->updateLegalProfile($owner, ['site_name' => 'Native Site Updated']);
privacyPdoCheck($service->publicLegalDocument('site-native', 'legal_notice') === null,
    'Changing legal source data invalidates the previously approved public document.');
$documentSnapshotAfter = $readModel->snapshot($owner);
$documentItemsAfter = ((array) $documentSnapshotAfter['data'])[PrivacyDocumentsModule::ID]['items'] ?? [];
$noticeAfter = array_values(array_filter(
    $documentItemsAfter,
    static fn (array $item): bool => ($item['id'] ?? null) === 'legal_notice',
))[0] ?? null;
privacyPdoCheck(is_array($noticeAfter) && ($noticeAfter['status'] ?? null) === 'À revalider',
    'MMW makes stale approved documents visibly require a new validation.');

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
privacyPdoCheck($auditRows === 13,
    'Privacy settings, legal documentation, scan, approval and denied mutations append shared CMS audit events.');

$migrations = $pdo->query('SELECT migration_id FROM schema_migrations ORDER BY migration_id')->fetchAll(PDO::FETCH_COLUMN);
$privacyMigrations = array_values(array_filter(
    $migrations,
    static fn (string $migration): bool => in_array($migration, ['003_privacy_core.sql', '004_privacy_documents.sql'], true),
));
privacyPdoCheck(
    $privacyMigrations === ['003_privacy_core.sql', '004_privacy_documents.sql'],
    'Migration runner records each native Privacy migration exactly once without assuming it is the last CMS migration.',
);

echo "Native privacy PDO integration tests passed.\n";
