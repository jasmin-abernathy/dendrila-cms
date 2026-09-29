<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Api\ApiKernel;
use LePotager\Cms\Api\AuthenticatedMember;
use LePotager\Cms\Api\AuthenticationFailure;
use LePotager\Cms\Api\AuthenticationService;
use LePotager\Cms\Api\AuthenticationStartResult;
use LePotager\Cms\Api\DeviceAuthenticator;
use LePotager\Cms\Api\DeviceTokenIssuer;
use LePotager\Cms\Api\IdentityProvider;
use LePotager\Cms\Api\SiteReadModel;
use LePotager\Cms\Api\SnapshotProjector;
use LePotager\Cms\Api\Unauthorized;
use LePotager\Cms\Api\VerifiedLogin;
use LePotager\Cms\Authorization\AccessDenied;
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
    public bool $denyReads = false;

    public function configuration(ActorContext $actor): array
    {
        if ($this->denyReads) {
            throw new AccessDenied('Read access is denied.');
        }
        return ['schema_version' => 1, 'site' => ['id' => $actor->siteId], 'modules' => []];
    }

    public function snapshot(ActorContext $actor): array
    {
        if ($this->denyReads) {
            throw new AccessDenied('Read access is denied.');
        }
        return ['revision' => 1, 'actor_id' => $actor->actorId, 'data' => (object) []];
    }
}

final class TestIdentityProvider implements IdentityProvider
{
    public function start(string $username, string $password, string $deviceName): AuthenticationStartResult
    {
        httpCheck($username === 'owner@example.test' && $password === 'test-password',
            'Auth start passes credentials unchanged to the identity provider.');
        return AuthenticationStartResult::challenge('challenge_identifier_123', ['totp'], 120);
    }

    public function verify(string $challengeId, string $method, string $code): VerifiedLogin
    {
        if ($challengeId !== 'challenge_identifier_123' || $method !== 'totp' || $code !== '123456') {
            throw new AuthenticationFailure('Authentication failed.');
        }
        return new VerifiedLogin(new AuthenticatedMember('site-test', 'actor-test'), 'Android test device');
    }

    public function pair(string $code): AuthenticatedMember
    {
        if ($code !== '12345678') {
            throw new AuthenticationFailure('Authentication failed.');
        }
        return new AuthenticatedMember('site-test', 'actor-test');
    }
}

final class TestDeviceTokenIssuer implements DeviceTokenIssuer
{
    public array $issued = [];

    public function issue(AuthenticatedMember $member, string $deviceName): array
    {
        $this->issued[] = [$member->siteId, $member->actorId, $deviceName];
        return ['device_token' => str_repeat('d', 64), 'expires_at' => null];
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

$readModel = new TestSiteReadModel();
$kernel = new ApiKernel(new TestDeviceAuthenticator(), $readModel);
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
httpCheck($snapshot->payload['actor_id'] === 'actor-test', 'Snapshot read model receives the authenticated actor.');
$readModel->denyReads = true;
$forbidden = $kernel->handle(new Request('GET', '/v1/config', [
    'x-lepotager-protocol' => '1',
    'x-lepotager-device-token' => 'a'.str_repeat('b', 63),
], ''));
httpCheck($forbidden->status === 403, 'CMS read permission failures map to HTTP 403.');
$readModel->denyReads = false;
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

$authUnavailable = $kernel->handle(new Request('POST', '/v1/auth/start', [
    'x-lepotager-protocol' => '1', 'content-type' => 'application/json',
], '{"username":"owner@example.test","password":"test-password","device_name":"Android test device"}'));
httpCheck($authUnavailable->status === 503
    && $authUnavailable->payload['error']['code'] === 'authentication_unavailable',
    'Auth routes report that site-specific identity integration is not configured.');

$issuer = new TestDeviceTokenIssuer();
$authKernel = new ApiKernel(new TestDeviceAuthenticator(), $readModel,
    new AuthenticationService(new TestIdentityProvider(), $issuer));
$authHeaders = ['x-lepotager-protocol' => '1', 'content-type' => 'application/json'];
$authStart = $authKernel->handle(new Request('POST', '/v1/auth/start', $authHeaders,
    '{"username":"owner@example.test","password":"test-password","device_name":"Android test device"}'));
httpCheck($authStart->status === 200 && $authStart->payload === [
    'status' => 'mfa_required', 'challenge_id' => 'challenge_identifier_123', 'methods' => ['totp'], 'expires_in' => 120,
], 'Auth start returns the protocol v1 MFA challenge contract.');
$authVerify = $authKernel->handle(new Request('POST', '/v1/auth/verify', $authHeaders,
    '{"challenge_id":"challenge_identifier_123","method":"totp","code":"123456"}'));
httpCheck($authVerify->status === 200 && $authVerify->payload === [
    'status' => 'authenticated', 'device_token' => str_repeat('d', 64), 'expires_at' => null,
], 'TOTP verification returns the protocol v1 device-token contract.');
$authPair = $authKernel->handle(new Request('POST', '/v1/auth/pair', $authHeaders,
    '{"code":"12345678","device_name":"Android paired device"}'));
httpCheck($authPair->status === 200 && $authPair->payload['status'] === 'authenticated'
    && count($issuer->issued) === 2 && $issuer->issued[1][2] === 'Android paired device',
    'Pairing consumes a code and issues a device token with the requested device name.');
$badTotp = $authKernel->handle(new Request('POST', '/v1/auth/verify', $authHeaders,
    '{"challenge_id":"challenge_identifier_123","method":"totp","code":"000000"}'));
httpCheck($badTotp->status === 401 && $badTotp->payload['error']['message'] === 'Authentication failed.',
    'Invalid credentials map to a generic authentication error.');
$authWrongMethod = $authKernel->handle(new Request('GET', '/v1/auth/start', ['x-lepotager-protocol' => '1'], ''));
httpCheck($authWrongMethod->status === 405 && ($authWrongMethod->headers['Allow'] ?? null) === 'POST',
    'Auth paths report POST as their allowed method.');

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

$projector = new SnapshotProjector();
$projected = $projector->project([
    [
        'id' => 'home', 'kind' => 'form', 'fields' => [
            ['id' => 'intro', 'source_field_id' => 'homepage_intro'],
        ],
        'source' => ['type_id' => 'page', 'content_id' => str_repeat('a', 32)],
    ],
    [
        'id' => 'agenda', 'kind' => 'calendar', 'fields' => [
            ['id' => 'service', 'source_field_id' => 'service_name'],
        ],
        'source' => ['type_id' => 'appointment', 'title_field' => 'service_name', 'start_field' => 'starts_at'],
    ],
],
    static fn (string $type, string $id): ?array => [
        'content_id' => $id, 'type_id' => $type, 'values' => ['homepage_intro' => 'Bonjour', 'site_id' => 'do-not-project'],
    ],
    static fn (string $type, int $limit): array => [
        ['content_id' => str_repeat('b', 32), 'slug' => 'guidance', 'status' => 'published',
            'values' => ['service_name' => 'Guidance', 'starts_at' => '2026-10-12T11:00:00+02:00']],
        ['content_id' => str_repeat('c', 32), 'slug' => 'old', 'status' => 'archived',
            'values' => ['service_name' => 'Ancien', 'starts_at' => '2026-10-12T11:00:00+02:00']],
    ],
);
httpCheck($projected['home'] === ['intro' => 'Bonjour'],
    'Form snapshots include only explicitly configured fields.');
httpCheck(count($projected['agenda']['items']) === 1
    && $projected['agenda']['items'][0]['title'] === 'Guidance'
    && $projected['agenda']['items'][0]['service'] === 'Guidance'
    && $projected['agenda']['items'][0]['start'] === '2026-10-12T11:00:00+02:00'
    && !array_key_exists('site_id', $projected['agenda']['items'][0]),
    'Collection snapshots project safe fields and omit archived content and CMS internals.');

echo "HTTP API contract tests passed.\n";
