<?php
declare(strict_types=1);
namespace Dendrila\Cms\Media;
use Dendrila\Cms\Http\UploadedFile;

final readonly class ImageStorage
{
    public const MIMES = ['image/jpeg', 'image/png', 'image/webp'];
    public string $root;
    public function __construct(string $root, public int $maxBytes = 10_000_000,
        public int $maxDimension = 6000, public int $maxPixels = 12_000_000)
    {
        if ($root === '' || $root[0] !== '/' || !is_dir($root) || is_link($root)) {
            throw new \InvalidArgumentException('An existing private absolute media directory is required.');
        }
        $this->root = (string) realpath($root);
        $public = realpath(dirname(__DIR__, 2) . '/public');
        if ($public && ($this->root === $public || str_starts_with($this->root, $public . '/'))) {
            throw new \InvalidArgumentException('Media storage must be outside the public directory.');
        }
        if ($maxBytes < 1 || $maxBytes > 25_000_000 || $maxDimension < 1 || $maxDimension > 10000
            || $maxPixels < 1 || $maxPixels > 20_000_000) throw new \InvalidArgumentException('Invalid image limits.');
    }

    public function inspect(UploadedFile $file): array
    {
        if ($file->error !== UPLOAD_ERR_OK) throw new \InvalidArgumentException('Le transfert du fichier a échoué.');
        if (!is_file($file->path) || is_link($file->path)) throw new \InvalidArgumentException('Fichier temporaire invalide.');
        $bytes = filesize($file->path);
        if ($bytes === false || $bytes < 1 || $bytes > $this->maxBytes) throw new \InvalidArgumentException('Image trop volumineuse ou vide.');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file->path);
        if (!in_array($mime, self::MIMES, true)) throw new \InvalidArgumentException('Format refusé : JPEG, PNG ou WebP uniquement.');
        $size = @getimagesize($file->path);
        if (!$size || ($size['mime'] ?? '') !== $mime || $size[0] < 1 || $size[1] < 1
            || max($size[0], $size[1]) > $this->maxDimension || $size[0] * $size[1] > $this->maxPixels) {
            throw new \InvalidArgumentException('Dimensions excessives ou image invalide.');
        }
        return ['mime' => $mime, 'bytes' => $bytes, 'width' => $size[0], 'height' => $size[1]];
    }

    public function original(UploadedFile $file): string
    {
        $key = bin2hex(random_bytes(24)) . '.original';
        if (!copy($file->path, $this->path($key)) || !chmod($this->path($key), 0600)) throw new \RuntimeException('Cannot store original.');
        return $key;
    }

    public function variants(string $path, int $rotation): array
    {
        $image = @imagecreatefromstring((string) file_get_contents($path));
        if (!$image) throw new \InvalidArgumentException('Image corrompue ou non décodable.');
        if ($rotation !== 0) {
            $image = imagerotate($image, -$rotation, imagecolorallocatealpha($image, 0, 0, 0, 127));
            if (!$image) throw new \RuntimeException('Image rotation failed.');
        }
        $result = [];
        foreach (['thumb' => 320, 'web' => 1920] as $kind => $max) {
            $scale = min(1, $max / max(imagesx($image), imagesy($image)));
            $w = max(1, (int) round(imagesx($image) * $scale));
            $h = max(1, (int) round(imagesy($image) * $scale));
            $target = imagecreatetruecolor($w, $h);
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagecopyresampled($target, $image, 0, 0, 0, 0, $w, $h, imagesx($image), imagesy($image));
            $key = bin2hex(random_bytes(24)) . '.webp';
            if (!imagewebp($target, $this->path($key), 82) || !is_file($this->path($key))
                || filesize($this->path($key)) < 1 || !chmod($this->path($key), 0600)) {
                throw new \RuntimeException('Cannot generate image derivative.');
            }
            $result[$kind] = ['storage_key' => $key, 'public_token' => bin2hex(random_bytes(24)),
                'width' => $w, 'height' => $h, 'bytes' => filesize($this->path($key))];
            unset($target);
        }
        return $result;
    }

    public function path(string $key): string
    {
        if (!preg_match('/^[a-f0-9]{48}\.(original|webp)$/D', $key)) throw new \InvalidArgumentException('Invalid storage key.');
        $path = $this->root . '/' . $key;
        if (is_link($path)) throw new \RuntimeException('Symlinks are not allowed in media storage.');
        return $path;
    }

    public function remove(string $key): bool
    {
        $path = $this->path($key);
        return !file_exists($path) || @unlink($path);
    }
}
