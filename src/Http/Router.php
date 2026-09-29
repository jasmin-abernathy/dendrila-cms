<?php
declare(strict_types=1);

namespace LePotager\Cms\Http;

final class Router
{
    /** @var array<string, callable(Request): Response> */
    private array $routes = [];

    /** @param callable(Request): Response $handler */
    public function add(string $method, string $path, callable $handler): void
    {
        $key = strtoupper($method) . ' ' . $path;
        if (isset($this->routes[$key])) {
            throw new \LogicException("Route '{$key}' is already registered.");
        }
        $this->routes[$key] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $key = $request->method . ' ' . $request->path;
        if (isset($this->routes[$key])) {
            return ($this->routes[$key])($request);
        }
        foreach ($this->routes as $registered => $_handler) {
            [, $path] = explode(' ', $registered, 2);
            if ($path === $request->path) {
                throw new HttpException(405, 'method_not_allowed', 'HTTP method is not allowed for this path.');
            }
        }
        throw new HttpException(404, 'not_found', 'API route does not exist.');
    }
}
