<?php
declare(strict_types=1);

namespace Dendrila\Cms\Oldweb;

use InvalidArgumentException;

/** Registry of themes installed by the maintainer alongside Dendrila's source. */
final class PortalThemes
{
    public const DEFAULT = 'canal57';

    public const AVAILABLE = [
        self::DEFAULT => [
            'label' => 'Canal 57',
            'stylesheet' => 'assets/oldweb-fixed-portal.css',
            'template' => 'portal-page.php',
        ],
    ];

    public static function selected(mixed $value): string
    {
        if (!is_string($value) || !isset(self::AVAILABLE[$value])) {
            throw new InvalidArgumentException('Sélectionne un thème installé.');
        }
        return $value;
    }

    /** @return array{label:string,stylesheet:string,template:string,defaults?:array} */
    public static function forRendering(string $value): array
    {
        return self::AVAILABLE[$value] ?? self::AVAILABLE[self::DEFAULT];
    }

    public static function initialAppearance(string $id): array
    {
        $theme = self::AVAILABLE[self::selected($id)];
        $defaults = PortalAppearance::defaults();
        $fields = array_replace_recursive($defaults, $theme['defaults'] ?? []);
        return PortalAppearance::edit($fields, $defaults, []);
    }
}
