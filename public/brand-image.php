<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

use Dendrila\Cms\Runtime\{Application, BrandImageStore};

// Public, immutable, re-encoded WebP only. Originals remain outside public_html.
if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD');
    exit;
}
$token = $_GET['token'] ?? null;
if (!is_string($token) || preg_match('/^[a-f0-9]{48}$/D', $token) !== 1) {
    http_response_code(404);
    exit;
}
try {
    $path = BrandImageStore::file(Application::site(), $token);
    if ($path === null) {
        http_response_code(404);
        exit;
    }
    $stream = @fopen($path, 'rb');
    if ($stream === false) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: image/webp');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Referrer-Policy: no-referrer');
    header('Cache-Control: public, max-age=31536000, immutable');
    header('Content-Length: ' . filesize($path));
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') fpassthru($stream);
    fclose($stream);
} catch (\Throwable $error) {
    error_log('Brand image delivery: ' . $error::class);
    http_response_code(404);
}
