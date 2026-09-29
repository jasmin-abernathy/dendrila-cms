<?php
declare(strict_types=1);

use LePotager\Cms\Api\ApiKernel;
use LePotager\Cms\Api\PdoDeviceAuthenticator;
use LePotager\Cms\Api\PdoSiteReadModel;
use LePotager\Cms\Http\HttpException;
use LePotager\Cms\Http\Request;
use LePotager\Cms\Http\Response;

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
    $kernel = new ApiKernel(new PdoDeviceAuthenticator($pdo), new PdoSiteReadModel($pdo));
    $kernel->handle(Request::fromGlobals())->emit();
} catch (Throwable $error) {
    error_log('MMW API bootstrap error: ' . $error::class . ': ' . $error->getMessage());
    Response::json(['error' => [
        'code' => 'service_unavailable',
        'message' => 'The API is temporarily unavailable.',
    ]], 503)->emit();
}
