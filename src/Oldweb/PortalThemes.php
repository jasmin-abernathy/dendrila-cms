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
        'borne-02' => [
            'label' => 'Borne 02',
            'stylesheet' => 'assets/borne-02/borne.css',
            'template' => 'portal-borne-02.php',
            'content' => 'borne-02-content.json',
            'defaults' => [
                'page' => ['background' => '#525F94', 'text' => '#F5F6FF', 'border' => 'ridge'],
                'banner' => ['background' => '#D9F45D', 'text' => '#171C30', 'border' => 'solid'],
                'title' => ['background' => '#101225', 'text' => '#FFFFFF', 'border' => 'none'],
                'menu' => ['background' => '#D9F45D', 'text' => '#101323', 'border' => 'outset'],
                'intro' => ['background' => '#161B3D', 'text' => '#FFFFFF', 'border' => 'ridge'],
                'news' => ['background' => '#161A31', 'text' => '#EBF8AA', 'border' => 'ridge'],
                'agenda' => ['background' => '#FFC66F', 'text' => '#2C193B', 'border' => 'ridge'],
                'archive' => ['background' => '#161A31', 'text' => '#F2D4FF', 'border' => 'ridge'],
                'focus' => ['background' => '#171D36', 'text' => '#E8EAFF', 'border' => 'ridge'],
            ],
        ],
        'signal-96' => [
            'label' => 'Signal 96',
            'stylesheet' => 'assets/signal-96/signal.css',
            'template' => 'portal-signal-96.php',
            'content' => 'signal-96-content.json',
            'defaults' => [
                'page' => ['background' => '#070A09', 'text' => '#E7EADC', 'border' => 'none'],
                'banner' => ['background' => '#111910', 'text' => '#E2C98A', 'border' => 'solid'],
                'title' => ['background' => '#070A09', 'text' => '#D6E2BA', 'border' => 'double'],
                'menu' => ['background' => '#070A09', 'text' => '#BDE878', 'border' => 'none'],
                'intro' => ['background' => '#111910', 'text' => '#F1F2DC', 'border' => 'solid'],
                'news' => ['background' => '#19190F', 'text' => '#EFD9A5', 'border' => 'solid'],
                'agenda' => ['background' => '#0D1C1E', 'text' => '#CAEEE6', 'border' => 'solid'],
                'archive' => ['background' => '#1A1522', 'text' => '#E5DAEE', 'border' => 'solid'],
                'focus' => ['background' => '#111910', 'text' => '#E7EADC', 'border' => 'solid'],
            ],
        ],
    ];

    public static function selected(mixed $value): string
    {
        if (!is_string($value) || !isset(self::AVAILABLE[$value])) {
            throw new InvalidArgumentException('Sélectionne un thème installé.');
        }
        return $value;
    }

    /** @return array{label:string,stylesheet:string,template:string,content?:string,defaults?:array} */
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
