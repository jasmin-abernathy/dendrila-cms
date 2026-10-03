<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Privacy\LegalDocumentRenderer;
use Dendrila\Cms\Privacy\LegalProfile;

function privacyDocumentCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$renderer = new LegalDocumentRenderer();
$profile = LegalProfile::normalize([
    'controller_name' => 'Association <Example>',
    'controller_address' => "1 rue Exemple\n57000 Metz",
    'privacy_contact' => 'privacy@example.test',
    'site_editor_name' => 'Association Example',
    'site_editor_address' => '1 rue Exemple, 57000 Metz',
    'entity_type' => 'association',
    'site_name' => 'Site Exemple',
    'site_url' => 'https://example.test',
    'public_contact_email' => 'contact@example.test',
    'publication_director' => 'Direction Example',
    'host_name' => 'Hébergeur France',
    'host_address' => '2 rue Serveur, Paris',
    'host_phone' => '+33 1 00 00 00 00',
    'collection_mode' => 'direct',
    'transfers_status' => 'no',
    'cookie_nonessential' => 'yes',
    'cookie_consent_status' => 'yes',
    'treatments' => [[
        'purpose' => 'Gérer les adhésions',
        'data_categories' => 'Identité et coordonnées',
        'legal_basis' => 'contract',
        'recipients' => 'Équipe associative',
        'retention' => 'Durée de l’adhésion puis archivage applicable',
        'mandatory' => 'contract',
        'consequences' => 'Impossible de gérer l’adhésion sans ces informations.',
    ]],
]);
$manifest = [
    'enabled' => true,
    'retention_days' => 180,
    'services' => [
        ['id' => 'youtube', 'label' => 'YouTube <iframe>', 'category' => 'external_media'],
    ],
];

foreach (LegalDocumentRenderer::KINDS as $kind) {
    $html = $renderer->render($kind, $profile, $manifest);
    privacyDocumentCheck($html !== '' && str_contains($html, 'dendrila-privacy-document'),
        "{$kind} renders a portable native document.");
    privacyDocumentCheck(!str_contains($html, '<Example>') && !str_contains($html, '<iframe>'),
        "{$kind} escapes profile and service labels.");
    privacyDocumentCheck($renderer->readiness($kind, $profile, $manifest)['errors'] === [],
        "{$kind} readiness accepts a complete profile.");
}

$cookies = $renderer->render('cookies', $profile, $manifest);
privacyDocumentCheck(str_contains($cookies, 'data-dendrila-consent-open="preferences"')
    && str_contains($cookies, 'YouTube &lt;iframe&gt;'),
    'Cookie document reuses the native consent preferences control and safe service labels.');

$incomplete = LegalProfile::defaults();
$noticeReadiness = $renderer->readiness('legal_notice', $incomplete, [
    'enabled' => false,
    'retention_days' => 180,
    'services' => [],
]);
privacyDocumentCheck(in_array('éditeur du site', $noticeReadiness['errors'], true)
    && in_array('nom de l’hébergeur', $noticeReadiness['errors'], true),
    'Incomplete legal notices are reported instead of being silently publishable.');

echo "Native privacy legal document tests passed.\n";
