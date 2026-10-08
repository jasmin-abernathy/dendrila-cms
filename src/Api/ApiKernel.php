<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Content\ContentNotFound;
use Dendrila\Cms\Content\IdempotencyConflict;
use Dendrila\Cms\Content\InvalidTransition;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Http\HttpException;
use Dendrila\Cms\Http\Request;
use Dendrila\Cms\Http\Response;
use Dendrila\Cms\Http\Router;
use Throwable;

final class ApiKernel
{
    private Router $router;

    public function __construct(
        private readonly DeviceAuthenticator $authenticator,
        private readonly SiteReadModel $readModel,
        private readonly ?AuthenticationService $authentication = null,
        private readonly ?SiteChangeHandler $changes = null,
        private readonly ?\Dendrila\Cms\Media\MediaService $media = null,
    ) {
        $this->router = new Router();
        $this->router->add('POST', '/v1/media', function (Request $request): Response {
            $actor = $this->authenticator->authenticate($request);
            if ($this->media === null) throw new HttpException(503, 'media_unavailable', 'Media storage is not configured.');
            $file = $request->uploadedMedia();
            return Response::json($this->media->upload($actor, $request->form, $file));
        });
        $this->router->add('GET', '/v1/media', function (Request $request): Response {
            $actor = $this->authenticator->authenticate($request);
            if ($this->media === null) throw new HttpException(503, 'media_unavailable', 'Media storage is not configured.');
            $filters = $request->query;
            $id = $filters['module_id'] ?? '';
            if (!is_string($id)) throw new \InvalidArgumentException('Invalid module_id.');
            unset($filters['module_id']);
            if (array_diff(array_keys($filters), ['query', 'mime', 'usage', 'limit', 'offset']) !== []) throw new \InvalidArgumentException('Invalid filters.');
            foreach (['limit', 'offset'] as $key) {
                if (isset($filters[$key])) {
                    if (!is_string($filters[$key]) || !ctype_digit($filters[$key])) throw new \InvalidArgumentException('Invalid pagination.');
                    $filters[$key] = (int) $filters[$key];
                }
            }
            return Response::json(['items' => $this->media->items($actor, $this->media->module($actor, $id), $filters)]);
        });
        $this->router->add('GET', '/v1/config', fn (Request $request): Response =>
            Response::json($this->readModel->configuration($this->authenticator->authenticate($request))));
        $this->router->add('GET', '/v1/snapshot', fn (Request $request): Response =>
            Response::json($this->readModel->snapshot($this->authenticator->authenticate($request))));
        $this->router->add('POST', '/v1/auth/start', fn (Request $request): Response =>
            Response::json($this->authentication()->start($request->jsonObject())));
        $this->router->add('POST', '/v1/auth/verify', fn (Request $request): Response =>
            Response::json($this->authentication()->verify($request->jsonObject())));
        $this->router->add('POST', '/v1/auth/pair', fn (Request $request): Response =>
            Response::json($this->authentication()->pair($request->jsonObject())));
        $this->router->add('POST', '/v1/changes', fn (Request $request): Response =>
            Response::json($this->changes()->apply(
                $this->authenticator->authenticate($request),
                $request->jsonObject(),
            )));
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
        } catch (AuthenticationFailure) {
            return self::error(401, 'authentication_failed', 'Authentication failed.');
        } catch (HttpException $error) {
            $methods = $error->status === 405 ? $this->router->allowedMethods($request->path) : [];
            $headers = $methods === [] ? [] : ['Allow' => implode(', ', $methods)];
            return self::error($error->status, $error->errorCode, $error->getMessage(), $headers);
        } catch (AccessDenied $error) {
            return self::error(403, 'forbidden', $error->getMessage());
        } catch (ContentNotFound $error) {
            return self::error(404, 'not_found', $error->getMessage());
        } catch (VersionConflict|IdempotencyConflict $error) {
            return self::error(409, 'conflict', $error->getMessage());
        } catch (InvalidTransition $error) {
            return self::error(409, 'invalid_transition', $error->getMessage());
        } catch (\JsonException|\InvalidArgumentException $error) {
            return self::error(422, 'validation_failed', $error->getMessage());
        } catch (Throwable $error) {
            error_log('MMW API error: ' . $error::class . ': ' . $error->getMessage());
            return self::error(500, 'internal_error', 'The server could not complete the request.');
        }
    }

    private function authentication(): AuthenticationService
    {
        if ($this->authentication === null) {
            throw new HttpException(503, 'authentication_unavailable',
                'The site identity provider is not configured.');
        }
        return $this->authentication;
    }

    private function changes(): SiteChangeHandler
    {
        if ($this->changes === null) {
            throw new HttpException(503, 'changes_unavailable', 'The change service is not configured.');
        }
        return $this->changes;
    }

    private static function error(int $status, string $code, string $message, array $headers = []): Response
    {
        return Response::json(['error' => ['code' => $code, 'message' => $message]], $status, $headers);
    }
}

