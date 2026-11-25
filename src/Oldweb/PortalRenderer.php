<?php
declare(strict_types=1);

namespace Dendrila\Cms\Oldweb;

final class PortalRenderer
{
    public static function example(array $theme, string $directory, string $language = PortalLocale::DEFAULT): array
    {
        $path = $directory . '/' . ($theme['content'] ?? 'portal-content.json');
        if ($language === 'en') {
            $localized = substr($path, 0, -5) . '.en';
            if (is_file($localized . '.php') || is_file($localized . '.json')) {
                $path = $localized . '.json';
            }
        }
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
        ?PortalStorage $storage = null,
        string $language = PortalLocale::DEFAULT
    ): void {
        $tr = PortalLocale::escape($language);
        require __DIR__ . '/' . $theme['template'];
    }
}
