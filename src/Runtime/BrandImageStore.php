<?php
declare(strict_types=1);

namespace Dendrila\Cms\Runtime;

use Dendrila\Cms\Http\UploadedFile;
use Dendrila\Cms\Media\ImageStorage;

/**
 * First-party brand images: one bounded, re-encoded WebP per upload.
 * Works without the optional media module. No user-supplied SVG, PHP,
 * HTML or unverified original is ever served from the public directory.
 */
final class BrandImageStore
{
    private const TOKEN = '/^[a-f0-9]{48}$/D';

    private static function directory(string $siteId, bool $create): string
    {
        if (preg_match('/^[a-zA-Z0-9_-]{1,128}$/D', $siteId) !== 1)
            throw new \InvalidArgumentException('Invalid brand image site.');
        $base = dirname(__DIR__, 2) . '/storage';
        if (!is_dir($base) || is_link($base))
            throw new \RuntimeException('Private storage unavailable.');
        $folder = $base . '/brand-assets';
        $path = $folder . '/' . $siteId;
        if ($create) {
            foreach ([$folder, $path] as $dir) {
                if (!is_dir($dir) && !mkdir($dir, 0700) && !is_dir($dir))
                    throw new \RuntimeException('Cannot prepare brand image directory.');
            }
        }
        foreach ([$folder, $path] as $dir) {
            if (is_link($dir)) throw new \RuntimeException('Private media symlinks are prohibited.');
        }
        return $path;
    }

    /** @return array{url:string,token:string} */
    public static function upload(string $siteId, UploadedFile $file): array
    {
        $directory = self::directory($siteId, true);
        // Reuse the native media validation for size, MIME and dimensions.
        $validator = new ImageStorage($directory, 8_000_000, 6000, 12_000_000);
        $info = $validator->inspect($file);
        $image = @imagecreatefromstring((string)file_get_contents($file->path));
        if (!$image instanceof \GdImage)
            throw new \InvalidArgumentException('Cette image ne peut pas être lue.');
        try {
            $max = max($info['width'], $info['height']);
            $ratio = min(1.0, 1920 / $max);
            $width = max(1, (int)round($info['width'] * $ratio));
            $height = max(1, (int)round($info['height'] * $ratio));
            $output = imagecreatetruecolor($width, $height);
            if (!$output instanceof \GdImage) throw new \RuntimeException('Cannot create brand image.');
            try {
                imagealphablending($output, false);
                imagesavealpha($output, true);
                if (!imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height,
                    $info['width'], $info['height'])) throw new \RuntimeException('Image conversion failed.');
                $token = bin2hex(random_bytes(24));
                $destination = $directory . '/' . $token . '.webp';
                if (!imagewebp($output, $destination, 85) || !is_file($destination)
                    || filesize($destination) < 1 || !chmod($destination, 0600)) {
                    @unlink($destination);
                    throw new \RuntimeException('Unable to save the brand image.');
                }
            } finally {
                imagedestroy($output);
            }
        } finally {
            imagedestroy($image);
        }
        $base = getenv('APP_BASE_URL') ?: '';
        if (!filter_var($base, FILTER_VALIDATE_URL)
            || parse_url($base, PHP_URL_SCHEME) !== 'https'
            || parse_url($base, PHP_URL_HOST) === null
            || parse_url($base, PHP_URL_USER) !== null
            || parse_url($base, PHP_URL_PASS) !== null
            || parse_url($base, PHP_URL_QUERY) !== null
            || parse_url($base, PHP_URL_FRAGMENT) !== null) {
            @unlink($destination);
            throw new \RuntimeException('HTTPS site address required for brand images.');
        }
        return ['token' => $token, 'url' => rtrim($base, '/') . '/brand-image.php?token=' . $token];
    }

    public static function file(string $siteId, string $token): ?string
    {
        if (preg_match(self::TOKEN, $token) !== 1) return null;
        $folder = self::directory($siteId, false);
        if (!is_dir($folder)) return null;
        $file = $folder . '/' . $token . '.webp';
        if (!is_file($file) || is_link($file)) return null;
        return $file;
    }

    public static function discard(string $siteId, string $token): void
    {
        $path = self::file($siteId, $token);
        if ($path !== null) @unlink($path);
    }
}
