<?php
declare(strict_types=1);

namespace Dendrila\Cms\Design;

use InvalidArgumentException;
use RuntimeException;

final readonly class DesignKitCatalog
{
    /** @var array<string, array<string, mixed>> */
    private array $themesByStyle;
    private string $catalogDirectory;

    public function __construct(string $catalogPath)
    {
        $realCatalog = realpath($catalogPath);
        if ($realCatalog === false || !is_file($realCatalog)) {
            throw new InvalidArgumentException('Design kit catalog does not exist.');
        }
        $this->catalogDirectory = dirname($realCatalog);

        try {
            $catalog = json_decode((string) file_get_contents($realCatalog), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new InvalidArgumentException('Design kit catalog contains invalid JSON.', previous: $error);
        }
        if (!is_array($catalog) || ($catalog['schema_version'] ?? null) !== 1
            || !is_array($catalog['themes'] ?? null) || !array_is_list($catalog['themes'])
            || $catalog['themes'] === [] || count($catalog['themes']) > 100) {
            throw new InvalidArgumentException('Invalid design kit catalog.');
        }

        $byStyle = [];
        $ids = [];
        foreach ($catalog['themes'] as $entry) {
            if (!is_array($entry)
                || !self::identifier($entry['id'] ?? null)
                || !self::identifier($entry['design_style_id'] ?? null)
                || !is_string($entry['version'] ?? null)
                || preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $entry['version']) !== 1
                || !self::relativePath($entry['path'] ?? null)
                || !self::manifestName($entry['manifest'] ?? null)) {
                throw new InvalidArgumentException('Invalid design kit catalog entry.');
            }
            if (isset($ids[$entry['id']]) || isset($byStyle[$entry['design_style_id']])) {
                throw new InvalidArgumentException('Duplicate design kit or design style mapping.');
            }
            $ids[$entry['id']] = true;
            $byStyle[$entry['design_style_id']] = $entry;
        }
        $this->themesByStyle = $byStyle;
    }

    /** @return array<string, mixed> */
    public function themeForStyle(string $designStyleId): array
    {
        if (!self::identifier($designStyleId) || !isset($this->themesByStyle[$designStyleId])) {
            throw new RuntimeException("No installed design kit matches '{$designStyleId}'.");
        }
        $entry = $this->themesByStyle[$designStyleId];
        $themeDirectory = $this->resolveDirectory($entry['path']);
        $manifestPath = $this->resolveFile($themeDirectory, $entry['manifest']);

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('Installed design kit manifest contains invalid JSON.', previous: $error);
        }
        if (!is_array($manifest)
            || ($manifest['schema_version'] ?? null) !== 1
            || ($manifest['id'] ?? null) !== $entry['id']
            || ($manifest['design_style_id'] ?? null) !== $entry['design_style_id']
            || ($manifest['version'] ?? null) !== $entry['version']
            || !self::relativePath($manifest['entrypoint'] ?? null)
            || !self::stringList($manifest['components'] ?? null, '/^[a-z][a-z0-9-]*$/')
            || !self::stringList($manifest['media_shapes'] ?? null, '/^[a-z][a-z0-9-]*$/')
            || !self::stringList($manifest['brand_tokens'] ?? null, '/^--dendrila-[a-z0-9-]+$/')
            || !self::stringList($manifest['required_style_tokens'] ?? null, '/^--dendrila-[a-z0-9-]+$/')) {
            throw new RuntimeException('Installed design kit manifest is incompatible.');
        }
        $accessibility = $manifest['accessibility'] ?? null;
        if (!is_array($accessibility)
            || ($accessibility['focus_visible'] ?? null) !== true
            || ($accessibility['reduced_motion'] ?? null) !== true
            || ($accessibility['no_autoplay_media'] ?? null) !== true) {
            throw new RuntimeException('Installed design kit accessibility contract is invalid.');
        }

        return [
            'id' => $manifest['id'],
            'design_style_id' => $manifest['design_style_id'],
            'version' => $manifest['version'],
            'root_path' => $themeDirectory,
            'entrypoint' => $manifest['entrypoint'],
            'entrypoint_path' => $this->resolveFile($themeDirectory, $manifest['entrypoint']),
            'components' => $manifest['components'],
            'media_shapes' => $manifest['media_shapes'],
            'brand_tokens' => $manifest['brand_tokens'],
            'required_style_tokens' => $manifest['required_style_tokens'],
        ];
    }

    private function resolveDirectory(string $relative): string
    {
        $resolved = realpath($this->catalogDirectory . DIRECTORY_SEPARATOR . $relative);
        if ($resolved === false || !is_dir($resolved) || !$this->insideCatalog($resolved)) {
            throw new RuntimeException('Design kit directory is missing or outside the catalog.');
        }
        return $resolved;
    }

    private function resolveFile(string $directory, string $relative): string
    {
        if (!self::relativePath($relative)) {
            throw new RuntimeException('Unsafe design kit file path.');
        }
        $resolved = realpath($directory . DIRECTORY_SEPARATOR . $relative);
        if ($resolved === false || !is_file($resolved)
            || !str_starts_with($resolved, $directory . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Design kit file is missing or outside its theme directory.');
        }
        return $resolved;
    }

    private function insideCatalog(string $path): bool
    {
        return $path === $this->catalogDirectory
            || str_starts_with($path, $this->catalogDirectory . DIRECTORY_SEPARATOR);
    }

    private static function identifier(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-z][a-z0-9-]*$/', $value) === 1;
    }

    private static function manifestName(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[A-Za-z0-9_.-]+\.json$/', $value) === 1
            && !str_contains($value, '..');
    }

    private static function relativePath(mixed $value): bool
    {
        if (!is_string($value) || $value === '' || str_starts_with($value, '/')
            || str_contains($value, '\\') || preg_match('#(^|/)\.\.?(/|$)#', $value) === 1) {
            return false;
        }
        return preg_match('#^[A-Za-z0-9_./-]+$#', $value) === 1;
    }

    private static function stringList(mixed $value, string $pattern): bool
    {
        if (!is_array($value) || !array_is_list($value) || $value === [] || count($value) > 100) {
            return false;
        }
        $seen = [];
        foreach ($value as $item) {
            if (!is_string($item) || preg_match($pattern, $item) !== 1 || isset($seen[$item])) {
                return false;
            }
            $seen[$item] = true;
        }
        return true;
    }
}
