<?php
declare(strict_types=1);

use Dendrila\Cms\Api\ApiKernel;
use Dendrila\Cms\Api\IdentityRuntimeFactory;
use Dendrila\Cms\Api\PdoSiteChangeService;
use Dendrila\Cms\Api\PdoSiteReadModel;
use Dendrila\Cms\Http\HttpException;
use Dendrila\Cms\Http\Request;
use Dendrila\Cms\Http\Response;

require dirname(__DIR__) . '/bootstrap.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (!getenv('DENDRILA_SITE_ID') && $path === '/') { header('Location: /install.php', true, 302); exit; }
if ($path === '/.well-known/lepotager-site-manager.json') {
    try {
        $manifest=\Dendrila\Cms\Api\MmwDiscoveryManifest::fromEnvironment(
            static fn(string $key):?string => getenv($key)===false?null:getenv($key)
        );
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        Response::json($manifest)->emit();
    } catch (\RuntimeException $e) {
        Response::json(['error'=>['code'=>'not_found','message'=>'Discovery is unavailable.']],404)->emit();
    }
    exit;
}

try {
    $dsn = getenv('DB_DSN') ?: '';
    $username = getenv('DB_USERNAME') ?: '';
    if ($dsn === '' || $username === '') {
        throw new RuntimeException('API database configuration is missing.');
    }
    $pdo = new PDO($dsn, $username, getenv('DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if (getenv('DENDRILA_SITE_ID') && !str_starts_with($path, '/v1/') && !str_starts_with($path, '/.well-known/')) {
        $html = (new \Dendrila\Cms\Runtime\PublicSite($pdo))->render($path);
        http_response_code($html === null ? 404 : 200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo $html ?? \Dendrila\Cms\Runtime\Application::page('Page introuvable', '<p>Cette page n’existe pas ou n’est pas publiée.</p>');
        exit;
    }
    $media = \Dendrila\Cms\Media\MediaFactory::fromEnvironment($pdo);
    $identity = IdentityRuntimeFactory::fromEnvironment($pdo);
    $kernel = new ApiKernel(
        $identity->authenticator,
        new PdoSiteReadModel($pdo, $media),
        authentication: $identity->authentication,
        changes: new PdoSiteChangeService($pdo, media: $media),
        media: $media,
    );
    $kernel->handle(Request::fromGlobals())->emit();
} catch (Throwable $error) {
    error_log('MMW API bootstrap error: ' . $error::class . ': ' . $error->getMessage());
    Response::json(['error' => [
        'code' => 'service_unavailable',
        'message' => 'The API is temporarily unavailable.',
    ]], 503)->emit();
}

