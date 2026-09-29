<?php
declare(strict_types=1);

namespace LePotager\Cms\Http;

final readonly class Response
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public int $status,
        public array $payload,
        public array $headers = [],
    ) {
    }

    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        return new self($status, $payload, $headers);
    }

    public function emit(): never
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: no-referrer');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo json_encode($this->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
