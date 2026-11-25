<?php
declare(strict_types=1);

if (!defined('DENDRILA_RETRO_ENTRY')) {
    http_response_code(404);
    return;
}

use Dendrila\Cms\Oldweb\PortalLocale;
use Dendrila\Cms\Oldweb\Standalone;

header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

require __DIR__ . '/PortalLocale.php';
$language = PortalLocale::selected(($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? ($_POST['lang'] ?? null) : ($_GET['lang'] ?? null))
    ?? PortalLocale::DEFAULT;

try {
    if (PHP_VERSION_ID < 80400) {
        throw new RuntimeException('Dans cPanel, sélectionne PHP 8.4 ou une version plus récente pour ce domaine.');
    }
    foreach (['fileinfo', 'mbstring', 'session'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException(PortalLocale::text($language, 'Dans cPanel, active l’extension PHP %s pour ce domaine.', $extension));
        }
    }

    require __DIR__ . '/PortalStorage.php';
    require __DIR__ . '/PortalAppearance.php';
    require __DIR__ . '/PortalThemes.php';
    require __DIR__ . '/PortalRenderer.php';
    require __DIR__ . '/Standalone.php';

    // An existing personal kit retains its data directory when updated.
    $configuration = __DIR__ . '/installation.php';
    if (is_link($configuration) || (file_exists($configuration) && !is_file($configuration))) {
        throw new RuntimeException('Le fichier de configuration du kit doit être un fichier ordinaire.');
    }
    $bundle = new Standalone(is_file($configuration) ? require $configuration : [
        'id' => substr(hash('sha256', dirname(__DIR__)), 0, 24),
    ]);
    $standaloneDirectory = $bundle->directory($_SERVER['DOCUMENT_ROOT'] ?? '', getenv('HOME') ?: '');
    $standaloneContentDirectory = __DIR__;
    if (!$bundle->enter($standaloneDirectory, $editor, $language)) {
        return;
    }
    $standaloneLogout = $editor ? $_SESSION['retro_token'] : null;
    require __DIR__ . '/portal-controller.php';
} catch (Throwable $error) {
    error_log('Dendrila Retro: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo PortalLocale::text(
        $language,
        $error instanceof RuntimeException && !$error instanceof JsonException
            ? $error->getMessage() : 'Portail momentanément indisponible.'
    );
}
