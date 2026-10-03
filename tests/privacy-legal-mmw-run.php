<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Api\PdoSiteReadModel;
use Dendrila\Cms\Api\PrivacyLegalModules;
use Dendrila\Cms\Api\PrivacyDocumentsModule;
use Dendrila\Cms\Privacy\LegalProfile;

function legalMmwCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function legalMmwExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        legalMmwCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$definitions = PrivacyLegalModules::definitions(true);
$safe = PdoSiteReadModel::safeModules($definitions);
legalMmwCheck(count($safe) === 3, 'Three native legal-documentation modules fit existing MMW v1 primitives.');
legalMmwCheck($safe[0]['kind'] === 'settings' && $safe[1]['kind'] === 'records' && $safe[2]['kind'] === 'records',
    'Legal documentation uses existing settings and records kinds only.');
legalMmwCheck(count($safe[0]['fields']) > 50 && count($safe[0]['fields']) < 100,
    'Legal settings stay within the bounded MMW field contract.');
legalMmwCheck(
    ($safe[0]['fields'][0]['hint'] ?? '') !== '',
    'Legal settings provide contextual field guidance through the existing MMW hint contract.',
);

$documentModule = PdoSiteReadModel::safeModules([PrivacyDocumentsModule::definition(true)])[0];
legalMmwCheck($documentModule['kind'] === 'records'
    && ($documentModule['actions'][0]['id'] ?? null) === 'approve'
    && ($documentModule['actions'][0]['allow_offline'] ?? true) === false,
    'Public privacy documents use a generic records module with explicit online-only approval.');

$documentSnapshot = PrivacyDocumentsModule::snapshot([
    [
        'kind' => 'legal_notice',
        'readiness' => ['errors' => [], 'warnings' => []],
        'current' => false,
        'has_approved_version' => false,
        'approved_at' => null,
    ],
    [
        'kind' => 'privacy',
        'readiness' => ['errors' => ['responsable du traitement'], 'warnings' => []],
        'current' => false,
        'has_approved_version' => false,
        'approved_at' => null,
    ],
    [
        'kind' => 'cookies',
        'readiness' => ['errors' => [], 'warnings' => []],
        'current' => true,
        'has_approved_version' => true,
        'approved_at' => '2026-10-03 08:00:00.000000',
    ],
]);
legalMmwCheck($documentSnapshot['items'][0]['status'] === 'Prêt à valider'
    && $documentSnapshot['items'][1]['status'] === 'À compléter'
    && $documentSnapshot['items'][2]['status'] === 'Publié',
    'Public privacy document state is projected without exposing generated HTML or audit evidence.');

$profile = LegalProfile::normalize([
    'controller_name' => 'Association Example',
    'privacy_contact' => 'privacy@example.test',
]);

$legalPlan = PrivacyLegalModules::apply($profile, PrivacyLegalModules::LEGAL_ID, 'update_fields', [
    'collection_mode' => 'both',
    'transfers_status' => 'no',
    'external_booking' => 'yes',
    'external_booking_tools' => 'Booking Example',
]);
$profile = LegalProfile::merge($profile, $legalPlan['changes']);
legalMmwCheck($profile['collection_mode'] === 'both' && $profile['external_booking_tools'] === 'Booking Example',
    'Legal settings mutation maps to the native legal profile.');

$treatmentPlan = PrivacyLegalModules::apply($profile, PrivacyLegalModules::TREATMENTS_ID, 'create_item', [
    'purpose' => 'Manage appointments',
    'data_categories' => 'Identity and appointment information',
    'legal_basis' => 'contract',
    'recipients' => 'Care team',
    'retention' => 'Applicable retention period',
    'mandatory' => 'contract',
]);
$profile = LegalProfile::merge($profile, $treatmentPlan['changes']);
$treatmentId = $treatmentPlan['result']['item']['id'];
legalMmwCheck(strlen($treatmentId) === 32 && count($profile['treatments']) === 1,
    'Creating a treatment generates a stable native id.');

$updatePlan = PrivacyLegalModules::apply($profile, PrivacyLegalModules::TREATMENTS_ID, 'update_item', [
    'item_id' => $treatmentId,
    'retention' => 'Updated retention period',
]);
$profile = LegalProfile::merge($profile, $updatePlan['changes']);
legalMmwCheck($profile['treatments'][0]['id'] === $treatmentId
    && $profile['treatments'][0]['retention'] === 'Updated retention period',
    'Updating a treatment preserves its stable id.');

$sourcePlan = PrivacyLegalModules::apply($profile, PrivacyLegalModules::SOURCES_ID, 'create_item', [
    'service_id' => 'booking_provider',
    'label' => 'Booking provider',
    'state' => 'active',
    'categories' => 'Name and email',
    'source' => 'Appointment form',
    'transfer_status' => 'unknown',
    'automated_decision' => 'no',
]);
$profile = LegalProfile::merge($profile, $sourcePlan['changes']);
legalMmwCheck(count($profile['indirect_services']) === 1
    && $profile['indirect_services'][0]['service_id'] === 'booking_provider',
    'Indirect sources are editable through a native records module.');

$snapshot = PrivacyLegalModules::snapshot($profile);
legalMmwCheck($snapshot[PrivacyLegalModules::TREATMENTS_ID]['items'][0]['id'] === $treatmentId
    && $snapshot[PrivacyLegalModules::LEGAL_ID]['controller_name'] === 'Association Example',
    'Legal profile projects into MMW-compatible settings and record data.');

$deletePlan = PrivacyLegalModules::apply($profile, PrivacyLegalModules::TREATMENTS_ID, 'delete_item', [
    'item_id' => $treatmentId,
]);
$profile = LegalProfile::merge($profile, $deletePlan['changes']);
legalMmwCheck($profile['treatments'] === [], 'Deleting a treatment removes only the addressed native row.');

legalMmwExpect(InvalidArgumentException::class, fn () => PrivacyLegalModules::apply(
    $profile,
    PrivacyLegalModules::LEGAL_ID,
    'update_fields',
    ['unknown' => 'fail closed'],
));
legalMmwExpect(RuntimeException::class, fn () => PrivacyLegalModules::assertReservedIdsAvailable([
    ['id' => PrivacyLegalModules::LEGAL_ID],
]));

echo "Native privacy legal MMW tests passed.\n";
