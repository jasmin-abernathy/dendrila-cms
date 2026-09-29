<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Api\ApiKernel;
use LePotager\Cms\Api\DeviceAuthenticator;
use LePotager\Cms\Api\SiteReadModel;
use LePotager\Cms\Api\Unauthorized;
use LePotager\Cms\Authorization\ActorContext;
use LePotager\Cms\Http\HttpException;
use LePotager\Cms\Http\Request;
use LePotager\Cms\Http\Response;
use LePotager\Cms\Http\Router;

function httpCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class TestDeviceAuthenticator implements DeviceAuthenticator
{
    public function authenticate(Request $request): ActorContext
    {
        if ($request->header('x-lepotager-device-token') !== 'a'.str_repeat('b', 63)) {
            throw new Unauthorized('A valid device token is required.');
        }
        return new ActorContext('site-test', 'actor-test', 'device-test', 'mmw');
    }
}

final class TestSiteReadModel implements SiteReadModel
{
    public function configuration(string $siteId): array
    {
        return ['schema_version' => 1, 'site' => ['id' => $siteId], 'modules' => []];
    }

    public function snapshot(string $siteId): array
    {
        return ['revision' => 1, 'data' => (object) []];
    }
}

$router = new Router();
$router->add('GET', '/test', static fn (Request $request): Response => Response::json(['ok' => true]));
httpCheck($router->dispatch(new Request('GET', '/test', [], ''))->payload['ok'] === true,
    'Router dispatches an exact method and path.');
try {
    $router->dispatch(new Request('POST', '/test', [], ''));
    throw new RuntimeException('Router accepted an unsupported method.');
} catch (HttpException $error) {
    httpCheck($error->status === 405, 'Router returns 405 for a known path and unsupported method.');
}

$kernel = new ApiKernel(new TestDeviceAuthenticator(), new TestSiteReadModel());
$config = $kernel->handle(new Request('GET', '/v1/config', [
    'x-lepotager-protocol' => '1',
    'x-lepotager-device-token' => 'a'.str_repeat('b', 63),
], ''));
httpCheck($config->status === 200 && $config->payload['site']['id'] === 'site-test',
    'Config endpoint uses the site resolved from the device token.');
$snapshot = $kernel->handle(new Request('GET', '/v1/snapshot', [
    'x-lepotager-protocol' => '1',
    'x-lepotager-device-token' => 'a'.str_repeat('b', 63),
], ''));
httpCheck($snapshot->status === 200 && $snapshot->payload['revision'] === 1,
    'Snapshot endpoint returns a revisioned payload.');
$unauthorized = $kernel->handle(new Request('GET', '/v1/config', [
    'x-lepotager-protocol' => '1',
], ''));
httpCheck($unauthorized->status === 401, 'Private routes require an authenticated device.');
$badProtocol = $kernel->handle(new Request('GET', '/v1/config', [], ''));
httpCheck($badProtocol->status === 426, 'Private routes require protocol version 1.');
$missing = $kernel->handle(new Request('GET', '/v1/missing', [
    'x-lepotager-protocol' => '1',
    'x-lepotager-device-token' => 'a'.str_repeat('b', 63),
], ''));
httpCheck($missing->status === 404, 'Unknown routes return 404.');
$wrongMethod = $kernel->handle(new Request('POST', '/v1/config', [
    'x-lepotager-protocol' => '1',
    'x-lepotager-device-token' => 'a'.str_repeat('b', 63),
], ''));
httpCheck($wrongMethod->status === 405 && ($wrongMethod->headers['Allow'] ?? null) === 'GET',
    'Config cannot be changed with POST and reports the supported method.');

$emptyObject = (new Request('POST', '/unused', ['content-type' => 'application/json'], '{}'))->jsonObject();
httpCheck($emptyObject === [], 'An empty JSON object is accepted.');
try {
    (new Request('POST', '/unused', ['content-type' => 'application/json'], '[]'))->jsonObject();
    throw new RuntimeException('A JSON list was accepted where an object was required.');
} catch (HttpException $error) {
    httpCheck($error->status === 400, 'A JSON list is rejected as the wrong request shape.');
}
try {
    (new Request('POST', '/unused', ['content-type' => 'text/plain'], '{}'))->jsonObject();
    throw new RuntimeException('A non-JSON request content type was accepted.');
} catch (HttpException $error) {
    httpCheck($error->status === 415, 'Non-JSON bodies are rejected explicitly.');
}
try {
    (new Request('POST', '/unused', ['content-type' => 'application/json'], str_repeat(' ', 1_048_577)))->jsonObject();
    throw new RuntimeException('An oversized JSON request was accepted.');
} catch (HttpException $error) {
    httpCheck($error->status === 413, 'Oversized JSON bodies are rejected before decoding.');
}

echo "HTTP API contract tests passed.\n";
