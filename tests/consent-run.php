<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Privacy\ConsentRenderer;
use Dendrila\Cms\Privacy\ConsentResourceRenderer;

function consentCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function consentExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        consentCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$renderer = new ConsentRenderer();
$manifest = [
    'enabled' => true,
    'layout' => 'card',
    'retention_days' => 120,
    'footer_link' => true,
    'fingerprint' => str_repeat('a', 64),
    'services' => [
        ['id' => 'analytics', 'label' => 'Mesure <script>alert(1)</script>', 'category' => 'statistics'],
        ['id' => 'video', 'label' => 'Vidéo externe', 'category' => 'external_media'],
    ],
];

$html = $renderer->render($manifest, '/assets', 'fr');
consentCheck(str_contains($html, 'role="dialog"') && str_contains($html, 'aria-modal="true"'),
    'Consent UI exposes an accessible modal dialog.');
consentCheck(str_contains($html, 'Tout refuser') && str_contains($html, 'Tout accepter'),
    'Accept and reject actions are both present.');
consentCheck(substr_count($html, 'class="dendrila-privacy-consent__choice"') >= 3,
    'Accept, reject and save use the same primary choice styling.');
consentCheck(!str_contains($html, ' checked'), 'Optional categories are never prechecked in server markup.');
consentCheck(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'),
    'Service labels are HTML-escaped.');
consentCheck(str_contains($html, '"fingerprint":"' . str_repeat('a', 64) . '"')
    && !str_contains($html, '</script><script>alert(1)</script>'),
    'Embedded consent configuration contains only normalized renderer data.');
consentCheck(str_contains($html, 'data-dendrila-consent-open="preferences"'),
    'A permanent manage-choices control is rendered when enabled.');
consentCheck(str_contains($html, '/assets/privacy-consent.js') && str_contains($html, '/assets/privacy-consent.css'),
    'Consent renderer references the portable front assets.');

$english = $renderer->render($manifest, 'https://static.example.test/privacy', 'en');
consentCheck(str_contains($english, 'Your privacy choices') && str_contains($english, 'Reject all'),
    'Consent UI provides English copy without a separate renderer.');

$disabled = $manifest;
$disabled['enabled'] = false;
consentCheck($renderer->render($disabled) === '', 'Disabled consent emits no banner or scripts.');
$empty = $manifest;
$empty['services'] = [];
consentCheck($renderer->render($empty) === '', 'No optional services means no unnecessary consent prompt.');
consentExpect(InvalidArgumentException::class, fn () => $renderer->render($manifest, 'javascript:alert(1)'));
$bad = $manifest;
$bad['services'][0]['category'] = 'invalid';
consentExpect(InvalidArgumentException::class, fn () => $renderer->render($bad));

$resources = new ConsentResourceRenderer();
$script = $resources->script('statistics', 'https://analytics.example.test/a.js', true, true);
consentCheck(str_contains($script, 'type="text/plain"')
    && str_contains($script, 'data-dendrila-src="https://analytics.example.test/a.js"')
    && !str_contains($script, ' src="https://analytics.example.test/a.js"'),
    'Deferred external scripts have no executable src before consent.');

$inline = $resources->inlineScript('marketing', 'window.example = "</script><script>alert(1)</script>";');
consentCheck(!str_contains($inline, 'window.example') && str_contains($inline, 'data-dendrila-inline-base64='),
    'Deferred inline scripts are encoded instead of embedded as executable markup.');

$iframe = $resources->iframe('external_media', 'https://video.example.test/embed/1', 'Vidéo test');
consentCheck(str_contains($iframe, 'data-dendrila-src="https://video.example.test/embed/1"')
    && !str_contains($iframe, ' src="https://video.example.test/embed/1"')
    && str_contains($iframe, 'data-dendrila-consent-allow="external_media"'),
    'Deferred iframes stay source-less and provide an explicit category opt-in.');

$stylesheet = $resources->stylesheet('functional', '/optional/widget.css');
consentCheck(str_contains($stylesheet, 'rel="preload"')
    && str_contains($stylesheet, 'data-dendrila-href="/optional/widget.css"')
    && !str_contains($stylesheet, ' href="/optional/widget.css"'),
    'Optional stylesheets are predeclared without loading before consent.');

consentExpect(InvalidArgumentException::class,
    fn () => $resources->script('statistics', 'http://insecure.example.test/a.js'));
consentExpect(InvalidArgumentException::class,
    fn () => $resources->iframe('invalid', 'https://example.test/embed', 'X'));

echo "Native privacy consent renderer tests passed.\n";
