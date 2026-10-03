<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Privacy\PrivacyDocumentCollector;
use Dendrila\Cms\Privacy\LegalProfile;
use Dendrila\Cms\Privacy\PrivacyInspector;
use Dendrila\Cms\Privacy\PrivacyRepository;
use Dendrila\Cms\Privacy\PrivacyScanRunner;
use Dendrila\Cms\Privacy\PrivacyService;
use Dendrila\Cms\Tests\Support\InMemoryCmsStore;

function privacyCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function privacyExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        privacyCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

final class TestPrivacyRepository implements PrivacyRepository
{
    /** @var array<string, array<string, mixed>> */
    public array $settings = [];
    /** @var array<string, array<string, array<string, mixed>>> */
    public array $scans = [];
    /** @var array<string, array<string, list<array<string, mixed>>>> */
    public array $findings = [];
    /** @var list<array<string, mixed>> */
    public array $audit = [];

    public function transaction(callable $operation): mixed
    {
        $snapshot = serialize([$this->settings, $this->scans, $this->findings, $this->audit]);
        try {
            return $operation();
        } catch (Throwable $error) {
            [$this->settings, $this->scans, $this->findings, $this->audit] = unserialize(
                $snapshot,
                ['allowed_classes' => false],
            );
            throw $error;
        }
    }

    public function findSettings(string $siteId): ?array
    {
        return $this->settings[$siteId] ?? null;
    }

    public function saveSettings(string $siteId, array $settings): void
    {
        $this->settings[$siteId] = $settings;
    }

    public function findLatestCompletedScan(string $siteId): ?array
    {
        $scans = array_values($this->scans[$siteId] ?? []);
        if ($scans === []) {
            return null;
        }
        usort($scans, static fn (array $a, array $b): int => strcmp($b['finished_at'], $a['finished_at']));
        return $scans[0];
    }

    public function listFindings(string $siteId, string $scanId): array
    {
        return $this->findings[$siteId][$scanId] ?? [];
    }

    public function saveCompletedScan(array $scan, array $findings): void
    {
        $publicScan = $scan;
        unset($publicScan['site_id']);
        $this->scans[$scan['site_id']][$scan['scan_id']] = $publicScan;
        $this->findings[$scan['site_id']][$scan['scan_id']] = $findings;
    }

    public function appendAudit(
        string $siteId,
        ?string $actorId,
        ?string $deviceId,
        string $action,
        ?string $entityId,
        array $details,
    ): void {
        $this->audit[] = compact('siteId', 'actorId', 'deviceId', 'action', 'entityId', 'details');
    }
}

$cmsStore = new InMemoryCmsStore();
$cmsStore->permissions['site-a']['owner-1'] = ['privacy.read', 'privacy.manage', 'privacy.scan'];
$cmsStore->permissions['site-a']['editor-1'] = ['privacy.read', 'privacy.scan'];
$cmsStore->permissions['site-b']['owner-2'] = ['privacy.read', 'privacy.manage', 'privacy.scan'];
$repository = new TestPrivacyRepository();
$service = new PrivacyService($repository, new AuthorizationService($cmsStore));
$owner = new ActorContext('site-a', 'owner-1', 'device-1');
$editor = new ActorContext('site-a', 'editor-1', 'device-2');

$initial = $service->state($owner);
privacyCheck($initial['settings'] === PrivacyService::defaultSettings(), 'Privacy defaults exist for every site.');
privacyCheck($initial['latest_scan'] === null && $initial['findings'] === [], 'A new site has no fabricated privacy scan.');
privacyCheck($initial['settings']['legal_profile']['supervisory_authority']
    === 'CNIL – Commission nationale de l’informatique et des libertés',
    'French legal-profile defaults provide the supervisory authority without fabricating organisation data.');

$legal = $service->updateLegalProfile($owner, [
    'controller_name' => 'Example Organisation',
    'privacy_contact' => 'privacy@example.test',
    'entity_type' => 'association',
    'collection_mode' => 'both',
    'indirect_services' => [[
        'label' => 'Booking provider',
        'categories' => 'Name, email address',
        'source' => 'Online booking form',
        'state' => 'manual',
    ]],
    'treatments' => [[
        'purpose' => 'Manage memberships',
        'data_categories' => 'Identity and contact details',
        'legal_basis' => 'contract',
        'retention' => 'During membership plus the applicable archive period',
        'mandatory' => 'contract',
    ]],
    'cookie_nonessential' => 'yes',
    'cookie_consent_status' => 'yes',
    'email_pixels' => 'no',
    'tracked_links' => 'no',
]);
privacyCheck(strlen($legal['treatments'][0]['id']) === 32
    && strlen($legal['indirect_services'][0]['id']) === 32,
    'Legal-profile repeatable rows receive stable native ids.');
$legalProgress = $service->legalProgress($owner);
privacyCheck($legalProgress['identity']['complete'] === true
    && $legalProgress['treatments']['complete'] === true
    && $legalProgress['flows']['complete'] === true
    && $legalProgress['cookies']['complete'] === true,
    'Legal-profile progress follows the same contextual completion principles as Dendrila Privacy.');
privacyExpect(InvalidArgumentException::class, fn () => $service->updateLegalProfile($owner, [
    'transfers_status' => 'sometimes',
]));
privacyExpect(InvalidArgumentException::class, fn () => LegalProfile::normalize([
    'unknown_field' => 'must fail closed',
]));
privacyExpect(AccessDenied::class, fn () => $service->updateLegalProfile($editor, [
    'controller_name' => 'Not allowed',
]));

$updated = $service->updateSettings($owner, [
    'consent' => ['enabled' => true, 'layout' => 'card', 'retention_days' => 90],
    'scan' => ['schedule' => 'weekly'],
    'legal_profile' => ['controller_name' => 'Example Organisation'],
    'external_practices' => [['id' => 'newsletter', 'label' => 'Newsletter']],
]);
privacyCheck($updated['consent']['enabled'] === true && $updated['consent']['footer_link'] === true,
    'Partial consent updates preserve native defaults.');
privacyCheck($updated['scan']['schedule'] === 'weekly', 'Scan schedule is stored in the native privacy state.');
privacyExpect(AccessDenied::class, fn () => $service->updateSettings($editor, ['scan' => ['schedule' => 'daily']]));
privacyExpect(InvalidArgumentException::class, fn () => $service->updateSettings($owner, [
    'consent' => ['retention_days' => 1],
]));

$inspector = new PrivacyInspector();
$findings = $inspector->inspect([
    [
        'id' => 'home',
        'url' => 'https://example.test/',
        'content' => '<script src="https://www.googletagmanager.com/gtag/js?id=G-TEST"></script>'
            . '<iframe src="https://www.youtube-nocookie.com/embed/demo"></iframe>',
    ],
    [
        'id' => 'contact',
        'url' => 'https://example.test/contact',
        'content' => '<script src="https://www.google.com/recaptcha/api.js"></script>',
    ],
]);
$byService = [];
foreach ($findings as $finding) {
    $byService[$finding['service_id']] = $finding;
}
privacyCheck(isset($byService['google_analytics'], $byService['youtube'], $byService['recaptcha']),
    'Built-in inspection detects representative tracker, media and functional services.');
privacyCheck($byService['google_analytics']['optional_tracking'] === true,
    'Google Analytics is marked optional for consent handling.');
privacyCheck($byService['recaptcha']['status'] === 'needs_review' && $byService['recaptcha']['optional_tracking'] === false,
    'reCAPTCHA remains contextual instead of being force-classified as optional tracking.');

$completed = $service->recordCompletedScan($editor, 'full', $findings);
privacyCheck($completed['scan']['summary']['total'] === 3, 'Completed privacy scan stores a bounded summary.');
privacyCheck($completed['scan']['summary']['optional_tracking'] === 2, 'Optional services are counted separately.');
$state = $service->state($owner);
privacyCheck($state['latest_scan']['scan_id'] === $completed['scan']['scan_id'] && count($state['findings']) === 3,
    'Latest privacy scan and findings are readable through the native service.');

$manifest = $service->consentManifest('site-a');
$manifestIds = array_column($manifest['services'], 'id');
privacyCheck($manifest['enabled'] === true && $manifest['layout'] === 'card' && $manifest['retention_days'] === 90,
    'Consent manifest exposes only renderer-safe settings.');
privacyCheck($manifestIds === ['google_analytics', 'youtube'],
    'Consent manifest includes optional detected services and excludes contextual services.');
privacyCheck(!array_key_exists('legal_profile', $manifest) && !array_key_exists('evidence', $manifest),
    'Consent manifest never exposes legal-profile data or scan evidence.');
privacyCheck($manifest['fingerprint'] === $service->consentManifest('site-a')['fingerprint'],
    'Consent fingerprint is stable while configuration and services do not change.');

$runner = new PrivacyScanRunner(
    $service,
    new PrivacyInspector(),
    new class implements PrivacyDocumentCollector {
        public function collect(ActorContext $actor, string $mode): array
        {
            privacyCheck($actor->siteId === 'site-a' && $mode === 'standard',
                'Scan runner passes the authenticated site and requested mode to the collector.');
            return [[
                'id' => 'runner-home',
                'url' => 'https://example.test/runner',
                'content' => '<script src="https://www.clarity.ms/tag/demo"></script>',
            ]];
        }
    },
);
$runnerResult = $runner->run($editor, 'standard');
privacyCheck($runnerResult['scan']['summary']['total'] === 1
    && $runnerResult['scan']['summary']['optional_tracking'] === 1,
    'Scan runner executes collection, inspection and persistence as one workflow.');

$otherSite = new ActorContext('site-b', 'owner-2');
$otherState = $service->state($otherSite);
privacyCheck($otherState['settings'] === PrivacyService::defaultSettings() && $otherState['latest_scan'] === null,
    'Privacy state remains isolated between sites.');
privacyCheck(count($repository->audit) === 4, 'Settings, legal-profile changes and both scan paths append privacy audit events.');

echo "Native privacy core tests passed.\n";
