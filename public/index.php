<?php
declare(strict_types=1);

use Dendrila\Cms\Api\ApiKernel;
use Dendrila\Cms\Api\IdentityRuntimeFactory;
use Dendrila\Cms\Api\PdoSiteChangeService;
use Dendrila\Cms\Api\PdoSiteReadModel;
use Dendrila\Cms\Http\HttpException;
use Dendrila\Cms\Http\Request;
use Dendrila\Cms\Http\Response;

require dirname(__DIR__) . '/vendor/autoload.php';

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

