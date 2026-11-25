<?php
declare(strict_types=1);

namespace Dendrila\Cms\Oldweb;

final class PortalRenderer
{
    public static function example(array $theme, string $directory): array
    {
        $path = $directory . '/' . ($theme['content'] ?? 'portal-content.json');
        $bundled = substr($path, 0, -5) . '.php';
        return is_file($bundled) ? require $bundled
            : json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    }

    public static function render(
        array $theme,
        array $appearance,
        array $content,
        bool $preview = false,
        bool $still = false,
        ?PortalStorage $storage = null
    ): void {
        require __DIR__ . '/' . $theme['template'];
    }
}
