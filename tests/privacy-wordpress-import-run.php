<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Privacy\WordPressPrivacyImport;

function wordpressImportCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function wordpressImportExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        wordpressImportCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$mapper = new WordPressPrivacyImport();
$mapped = $mapper->map([
    'pixel_trackers_manager_settings' => [
        'schedule' => 'weekly',
        'consent_enabled' => 1,
        'consent_layout' => 'card',
        'consent_retention_days' => '120',
        'consent_footer_link' => '1',
        'privacy_page_id' => 42,
        'consent_style' => 'inherit',
    ],
    'pixel_trackers_manager_legal_profile' => [
        'controller_name' => 'Association Example',
        'privacy_contact' => 'privacy@example.test',
        'entity_type' => 'association',
        'company_country' => 'fr',
        'collection_mode' => 'both',
        'wizard_reviewed_once' => 1,
        'treatments' => [[
            'purpose' => 'Manage memberships',
            'data_categories' => 'Identity and contact details',
            'legal_basis' => 'contract',
            'recipients' => 'Association team',
            'retention' => 'Membership duration',
            'mandatory' => 'contract',
        ]],
        'indirect_services' => [[
            'service_id' => 'booking_provider',
            'label' => 'Booking provider',
            'state' => 'manual',
            'categories' => 'Name and email',
            'source' => 'Booking form',
            'transfer_status' => 'unknown',
            'automated_decision' => 'no',
        ]],
    ],
    'pixel_trackers_manager_scan_current' => [
        'version' => 6,
        'plugin_version' => '0.0.5',
        'generated_at' => '2026-10-03T08:00:00+00:00',
        'coverage' => ['mode' => 'full'],
        'findings' => [
            [
                'id' => 'google_analytics',
                'label' => 'Google Analytics',
                'category' => 'Mesure d’audience',
                'is_tracking' => true,
                'active_tracking' => true,
                'blocked_by_consent' => false,
                'observed_html' => true,
                'tracking_state' => 'active',
                'tracking_basis' => 'technical_html',
                'urls' => ['https://example.test/'],
            ],
            [
                'id' => 'youtube',
                'label' => 'YouTube',
                'category' => 'Contenu embarqué',
                'is_tracking' => false,
                'active_tracking' => false,
                'blocked_by_consent' => true,
                'observed_html' => true,
                'tracking_state' => 'blocked',
                'urls' => ['https://example.test/video'],
            ],
            [
                'id' => 'recaptcha',
                'label' => 'Google reCAPTCHA',
                'category' => 'Sécurité / anti-spam',
                'is_tracking' => true,
                'active_tracking' => true,
                'blocked_by_consent' => false,
                'observed_html' => true,
            ],
        ],
    ],
]);

wordpressImportCheck($mapped['settings_changes']['consent']['enabled'] === true
    && $mapped['settings_changes']['consent']['layout'] === 'card'
    && $mapped['settings_changes']['consent']['retention_days'] === 120
    && $mapped['settings_changes']['scan']['schedule'] === 'weekly',
    'WordPress settings map to native consent and scan settings.');
wordpressImportCheck($mapped['settings_changes']['legal_profile']['controller_name'] === 'Association Example'
    && $mapped['settings_changes']['legal_profile']['wizard_reviewed_once'] === true,
    'WordPress legal profile maps to the native legal profile.');
wordpressImportCheck(strlen($mapped['settings_changes']['legal_profile']['treatments'][0]['id']) === 32
    && strlen($mapped['settings_changes']['legal_profile']['indirect_services'][0]['id']) === 32,
    'Imported repeatable legal rows receive native stable ids.');
wordpressImportCheck($mapped['scan_mode'] === 'full' && count($mapped['findings']) === 3,
    'WordPress full scan is prepared as a native full scan.');
$byService = [];
foreach ($mapped['findings'] as $finding) {
    $byService[$finding['service_id']] = $finding;
}
wordpressImportCheck($byService['google_analytics']['category'] === 'statistics'
    && $byService['google_analytics']['optional_tracking'] === true,
    'Audience tracker maps to native statistics and optional tracking.');
wordpressImportCheck($byService['youtube']['category'] === 'external_media'
    && $byService['youtube']['kind'] === 'third_party_service'
    && $byService['youtube']['optional_tracking'] === true,
    'Consent-blocked external media remains optional after import.');
wordpressImportCheck($byService['recaptcha']['category'] === 'functional'
    && $byService['recaptcha']['optional_tracking'] === false,
    'reCAPTCHA remains contextual and is not force-imported as optional tracking.');
wordpressImportCheck(count($mapped['warnings']) >= 2,
    'WordPress-only page/style settings are reported as ignored instead of silently copied.');

$legalOnly = $mapper->map([
    'legal_profile' => ['controller_name' => 'Legal only'],
]);
wordpressImportCheck(!array_key_exists('consent', $legalOnly['settings_changes'])
    && $legalOnly['settings_changes']['legal_profile']['controller_name'] === 'Legal only',
    'A legal-profile-only import does not reset existing native consent settings.');

wordpressImportExpect(InvalidArgumentException::class, fn () => $mapper->map([
    'settings' => ['consent_enabled' => 'yes'],
]));
wordpressImportExpect(InvalidArgumentException::class, fn () => $mapper->map([
    'scan' => ['findings' => ['not-an-object']],
]));

echo "WordPress privacy import mapping tests passed.\n";
