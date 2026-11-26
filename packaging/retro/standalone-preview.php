<?php
declare(strict_types=1);

use Dendrila\Cms\Oldweb\PortalLocale;
use Dendrila\Cms\Oldweb\PortalRenderer;
use Dendrila\Cms\Oldweb\PortalThemes;

require __DIR__ . '/PortalLocale.php';
require __DIR__ . '/PortalAppearance.php';
require __DIR__ . '/PortalThemes.php';
require __DIR__ . '/PortalRenderer.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header('Content-Type: text/html; charset=UTF-8');
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    return;
}
$language = PortalLocale::selected(PHP_SAPI === 'cli' ? ($argv[2] ?? null) : ($_GET['lang'] ?? null)) ?? PortalLocale::DEFAULT;
try {
    $id = PortalThemes::selected(PHP_SAPI === 'cli' ? ($argv[1] ?? null) : ($_GET['theme'] ?? null));
} catch (InvalidArgumentException) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, PortalLocale::text($language, 'Sélectionne un thème installé.') . "\n");
        exit(2);
    }
    http_response_code(404);
    return;
}
if ($method !== 'HEAD') {
    $theme = PortalThemes::forRendering($id);
    PortalRenderer::render(
        $theme,
        PortalThemes::initialAppearance($id),
        PortalRenderer::example($theme, __DIR__, $language),
        language: $language
    );
}
