<?php
declare(strict_types=1);

if (!defined('DENDRILA_RETRO_ENTRY')) {
    http_response_code(404);
    return;
}

use Dendrila\Cms\Oldweb\Standalone;

header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

try {
    if (PHP_VERSION_ID < 80400) {
        throw new RuntimeException('Dans cPanel, sélectionne PHP 8.4 ou une version plus récente pour ce domaine.');
    }
    foreach (['fileinfo', 'mbstring', 'session'] as $extension) {
        if (!extension_loaded($extension)) {
            throw new RuntimeException('Dans cPanel, active l’extension PHP ' . $extension . ' pour ce domaine.');
        }
    }

    require __DIR__ . '/PortalStorage.php';
    require __DIR__ . '/PortalAppearance.php';
    require __DIR__ . '/PortalThemes.php';
    require __DIR__ . '/Standalone.php';

    $bundle = new Standalone(require __DIR__ . '/installation.php');
    $standaloneDirectory = $bundle->directory($_SERVER['DOCUMENT_ROOT'] ?? '', getenv('HOME') ?: '');
    $standaloneContent = __DIR__ . '/portal-content.json';
    if (!$bundle->enter($standaloneDirectory, $editor)) {
        return;
    }
    $standaloneLogout = $editor ? $_SESSION['retro_token'] : null;
    require __DIR__ . '/portal-controller.php';
} catch (Throwable $error) {
    error_log('Dendrila Retro: ' . $error->getMessage());
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $error instanceof RuntimeException && !$error instanceof JsonException
        ? $error->getMessage() : 'Le portail est momentanément indisponible.';
}
