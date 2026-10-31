<?php
declare(strict_types=1);
require dirname(__DIR__) . '/bootstrap.php';

// Only re-encoded current derivatives are served; originals never have a public route.
try {
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        http_response_code(405); header('Allow: GET, HEAD'); exit;
    }
    if (!is_string($_GET['site'] ?? null) || !is_string($_GET['token'] ?? null)) {
        throw new Dendrila\Cms\Content\ContentNotFound('Image not found.');
    }
    $pdo = new PDO(getenv('DB_DSN') ?: '', getenv('DB_USERNAME') ?: '', getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $media = Dendrila\Cms\Media\MediaFactory::fromEnvironment($pdo);
    if ($media === null) throw new Dendrila\Cms\Content\ContentNotFound('Image not found.');
    $path = $media->publicImage($_GET['site'], $_GET['token']);
    $stream = @fopen($path, 'rb');
    if ($stream === false) throw new Dendrila\Cms\Content\ContentNotFound('Image not found.');
    $stat = fstat($stream);
    header('Content-Type: image/webp');
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; sandbox");
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    if (is_array($stat)) header('Content-Length: ' . $stat['size']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') fpassthru($stream);
    fclose($stream);
} catch (Dendrila\Cms\Content\ContentNotFound) {
    http_response_code(404);
} catch (Throwable $error) {
    error_log('Media delivery failed: ' . $error::class);
    http_response_code(503);
}
