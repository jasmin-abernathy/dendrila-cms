<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

use LePotager\Cms\Http\HttpException;
use LePotager\Cms\Http\Request;
use LePotager\Cms\Http\Response;
use LePotager\Cms\Http\Router;
use Throwable;

final class ApiKernel
{
    private Router $router;

    public function __construct(
        private readonly DeviceAuthenticator $authenticator,
        private readonly SiteReadModel $readModel,
    ) {
        $this->router = new Router();
        $this->router->add('GET', '/v1/config', fn (Request $request): Response =>
            Response::json($this->readModel->configuration($this->authenticator->authenticate($request)->siteId)));
        $this->router->add('GET', '/v1/snapshot', fn (Request $request): Response =>
            Response::json($this->readModel->snapshot($this->authenticator->authenticate($request)->siteId)));
    }

    public function handle(Request $request): Response
    {
        $protocol = $request->header('x-lepotager-protocol');
        if ($protocol !== '1') {
            return self::error(426, 'unsupported_protocol', 'Send X-Lepotager-Protocol: 1.');
        }
        try {
            return $this->router->dispatch($request);
        } catch (Unauthorized $error) {
            return self::error(401, 'unauthorized', $error->getMessage());
        } catch (HttpException $error) {
            $headers = $error->status === 405 ? ['Allow' => 'GET'] : [];
            return self::error($error->status, $error->errorCode, $error->getMessage(), $headers);
        } catch (Throwable $error) {
            error_log('MMW API error: ' . $error::class . ': ' . $error->getMessage());
            return self::error(500, 'internal_error', 'The server could not complete the request.');
        }
    }

    private static function error(int $status, string $code, string $message, array $headers = []): Response
    {
        return Response::json(['error' => ['code' => $code, 'message' => $message]], $status, $headers);
    }
}
