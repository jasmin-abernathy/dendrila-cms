<?php
declare(strict_types=1);
namespace Dendrila\Cms\Http;

/** The path is supplied by PHP or a trusted test adapter, never a request field. */
final readonly class UploadedFile
{
    public function __construct(public string $path, public string $name, public int $error = UPLOAD_ERR_OK) {}

    public static function fromPhp(array $file): self
    {
        if (!is_int($file['error'] ?? null) || !is_string($file['tmp_name'] ?? null)
            || !is_string($file['name'] ?? null)) {
            throw new \InvalidArgumentException('Invalid uploaded file.');
        }
        if ($file['error'] === UPLOAD_ERR_OK && !is_uploaded_file($file['tmp_name'])) {
            throw new \InvalidArgumentException('Untrusted uploaded file.');
        }
        return new self($file['tmp_name'], $file['name'], $file['error']);
    }
}
