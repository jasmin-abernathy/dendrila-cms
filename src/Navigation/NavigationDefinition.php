<?php
declare(strict_types=1);

namespace Dendrila\Cms\Navigation;

final class NavigationDefinition
{
    /** @param list<array<string,mixed>> $items @return list<array{id:string,label:string,url:string,visible:bool}> */
    public static function normalizeItems(array $items): array
    {
        if (!array_is_list($items) || count($items) > 50) {
            throw new \InvalidArgumentException('Navigation items must be a list of at most 50 links.');
        }

        $safe = [];
        $ids = [];
        foreach ($items as $item) {
            if (!is_array($item) || array_diff(array_keys($item), ['id', 'label', 'url', 'visible']) !== []) {
                throw new \InvalidArgumentException('Navigation item contains unsupported fields.');
            }
            $id = self::identifier($item['id'] ?? null, 'navigation item id');
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException('Navigation item ids must be unique inside a menu.');
            }
            $ids[$id] = true;

            $label = self::text($item['label'] ?? null, 'navigation label', 190);
            $url = self::safeLink($item['url'] ?? null);
            $visible = $item['visible'] ?? true;
            if (!is_bool($visible)) {
                throw new \InvalidArgumentException('Navigation visibility must be boolean.');
            }
            $safe[] = ['id' => $id, 'label' => $label, 'url' => $url, 'visible' => $visible];
        }
        return $safe;
    }

    /** @param array<string,mixed> $menu @return array<string,mixed> */
    public static function normalizeMenu(array $menu): array
    {
        foreach (['site_id', 'menu_id', 'label', 'location', 'items', 'version'] as $key) {
            if (!array_key_exists($key, $menu)) {
                throw new \InvalidArgumentException("Navigation menu is missing {$key}.");
            }
        }
        if (!is_string($menu['site_id']) || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D', $menu['site_id']) !== 1) {
            throw new \InvalidArgumentException('Invalid navigation site id.');
        }
        $menuId = self::identifier($menu['menu_id'], 'navigation menu id');
        $location = self::identifier($menu['location'], 'navigation location');
        $label = self::text($menu['label'], 'navigation menu label', 190);
        if (!is_array($menu['items'])) {
            throw new \InvalidArgumentException('Navigation items must be an array.');
        }
        if (!is_int($menu['version']) || $menu['version'] < 1) {
            throw new \InvalidArgumentException('Invalid navigation version.');
        }

        return [
            'site_id' => $menu['site_id'],
            'menu_id' => $menuId,
            'label' => $label,
            'location' => $location,
            'items' => self::normalizeItems($menu['items']),
            'version' => $menu['version'],
            'created_at' => is_string($menu['created_at'] ?? null) ? $menu['created_at'] : null,
            'updated_at' => is_string($menu['updated_at'] ?? null) ? $menu['updated_at'] : null,
        ];
    }

    public static function identifier(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $value) !== 1) {
            throw new \InvalidArgumentException("Invalid {$label}.");
        }
        return $value;
    }

    private static function text(mixed $value, string $label, int $maxLength): string
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("Invalid {$label}.");
        }
        return trim($value);
    }

    private static function safeLink(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Invalid navigation URL.');
        }
        $url = trim($value);
        if ($url === '' || str_starts_with($url, '//') || str_contains($url, "\0")) {
            throw new \InvalidArgumentException('Invalid navigation URL.');
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }
        if (str_starts_with(strtolower($url), 'mailto:')) {
            $email = substr($url, 7);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                return $url;
            }
        }
        if (filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') {
            return $url;
        }
        throw new \InvalidArgumentException('Navigation URLs must be root-relative, fragment, mailto or HTTPS.');
    }
}
