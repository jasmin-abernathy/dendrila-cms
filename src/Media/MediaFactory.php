<?php
declare(strict_types=1);
namespace Dendrila\Cms\Media;
final class MediaFactory
{
    public static function fromEnvironment(\PDO $pdo): ?MediaService
    {
        $root = getenv('MEDIA_PRIVATE_ROOT') ?: '';
        $url = getenv('MEDIA_PUBLIC_ENDPOINT') ?: '';
        if ($root === '' && $url === '') return null;
        $limits = [];
        foreach (['MEDIA_MAX_BYTES' => 10000000, 'MEDIA_MAX_DIMENSION' => 6000, 'MEDIA_MAX_PIXELS' => 12000000] as $name => $default) {
            $raw = getenv($name);
            if ($raw !== false && (!ctype_digit($raw) || strlen($raw) > 9)) throw new \RuntimeException('Invalid media limit configuration.');
            $limits[] = $raw === false ? $default : (int) $raw;
        }
        return new MediaService($pdo, new ImageStorage($root, ...$limits), $url);
    }
}
