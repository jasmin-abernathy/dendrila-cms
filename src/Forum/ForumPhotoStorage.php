<?php
declare(strict_types=1);
namespace Dendrila\Cms\Forum;

/** Re-encodes images to WebP in a private folder, removing EXIF and active content. */
final readonly class ForumPhotoStorage
{
    private string $directory;

    public function __construct(string $directory)
    {
        if (!str_starts_with($directory, '/') || $directory === '/') {
            throw new \InvalidArgumentException('Le dossier photo doit être un chemin absolu privé.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Impossible de créer le dossier photo.');
        }
        $real = realpath($directory);
        if ($real === false) { throw new \RuntimeException('Dossier photo inaccessible.'); }
        $configuredRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
        $docRoot = $configuredRoot !== '' ? realpath($configuredRoot) : false;
        if ($docRoot !== false && ($real === $docRoot || str_starts_with($real, $docRoot . '/'))) {
            throw new \InvalidArgumentException('Le dossier photo ne peut pas être dans le webroot.');
        }
        $this->directory = $real;
    }

    /** @return array{width:int,height:int,byte_size:int} */
    public function save(string $id, string $bytes): array
    {
        ForumRules::id($id);
        if ($bytes === '' || strlen($bytes) > ForumRules::MAX_UPLOAD_BYTES) {
            throw new \InvalidArgumentException('Photo vide ou trop volumineuse (max 6 Mio).');
        }
        if (!function_exists('imagecreatefromstring') || !function_exists('imagewebp')) {
            throw new \RuntimeException('L’extension GD avec WebP est requise.');
        }
        $meta = @getimagesizefromstring($bytes);
        if (!is_array($meta) || !in_array($meta[2] ?? null, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw new \InvalidArgumentException('Formats acceptés : JPEG, PNG, WebP.');
        }
        $w = (int) $meta[0]; $h = (int) $meta[1];
        if ($w < 16 || $h < 16 || $w > 6000 || $h > 6000 || $w * $h > 12_000_000) {
            throw new \InvalidArgumentException('Dimensions de photo non autorisées.');
        }
        $source = @imagecreatefromstring($bytes);
        if (!$source instanceof \GdImage) { throw new \InvalidArgumentException('Photo illisible.'); }
        $scale = min(1.0, 2048 / max($w, $h));
        $outW = max(1, (int) round($w * $scale)); $outH = max(1, (int) round($h * $scale));
        $target = imagecreatetruecolor($outW, $outH);
        if (!$target instanceof \GdImage) {
            imagedestroy($source);
            throw new \RuntimeException('Transformation impossible.');
        }
        try {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $clear = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $outW, $outH, $clear);
            imagecopyresampled($target, $source, 0, 0, 0, 0, $outW, $outH, $w, $h);
            $temp = tempnam($this->directory, '.upload-');
            if ($temp === false) { throw new \RuntimeException('Stockage photo indisponible.'); }
            try {
                if (!imagewebp($target, $temp, 82)) { throw new \RuntimeException('Encodage WebP impossible.'); }
                chmod($temp, 0600);
                $size = filesize($temp);
                if ($size === false || $size < 1 || !rename($temp, $this->path($id))) {
                    throw new \RuntimeException('Enregistrement photo impossible.');
                }
                return ['width' => $outW, 'height' => $outH, 'byte_size' => (int) $size];
            } finally {
                if (is_file($temp)) { unlink($temp); }
            }
        } finally {
            imagedestroy($source);
            imagedestroy($target);
        }
    }

    public function read(string $id): string
    {
        $result = @file_get_contents($this->path(ForumRules::id($id)));
        if (!is_string($result)) { throw new \RuntimeException('Photo introuvable.'); }
        return $result;
    }

    public function delete(string $id): void
    {
        $path = $this->path(ForumRules::id($id));
        if (is_file($path)) { unlink($path); }
    }

    private function path(string $id): string { return $this->directory . '/' . $id . '.webp'; }
}
