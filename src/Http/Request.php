<?php
declare(strict_types=1);

namespace LePotager\Cms\Http;

final readonly class Request
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $method,
        public string $path,
        public array $headers,
        public string $body,
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() ?: [] as $name => $value) {
                if (is_string($name) && is_scalar($value)) {
                    $headers[strtolower($name)] = trim((string) $value);
                }
            }
        }
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_') && is_string($value)) {
                $header = strtolower(str_replace('_', '-', substr($name, 5)));
                $headers[$header] ??= trim($value);
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] ??= trim((string) $_SERVER['CONTENT_TYPE']);
        }

        $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($uriPath) && $uriPath !== '' ? $uriPath : '/';
        $body = file_get_contents('php://input');
        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            $path,
            $headers,
            is_string($body) ? $body : '',
        );
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** @return array<string, mixed> */
    public function jsonObject(): array
    {
        $contentType = strtolower(trim(explode(';', $this->header('content-type') ?? '')[0]));
        if ($contentType !== 'application/json') {
            throw new HttpException(415, 'unsupported_media_type', 'Content-Type must be application/json.');
        }
        if (strlen($this->body) > 1_048_576) {
            throw new HttpException(413, 'request_too_large', 'JSON request body exceeds 1 MiB.');
        }
        try {
            $value = json_decode($this->body, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new HttpException(400, 'invalid_json', 'Request body is not valid JSON.', $error);
        }
        if (!$value instanceof \stdClass) {
            throw new HttpException(400, 'invalid_json_shape', 'Request body must be a JSON object.');
        }
        return (array) $value;
    }
}
