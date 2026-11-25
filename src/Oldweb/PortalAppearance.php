<?php
declare(strict_types=1);

namespace Dendrila\Cms\Oldweb;

use InvalidArgumentException;

final class PortalAppearance
{
    public const ZONES = [
        'page' => ['Fond du site', '#2455E5', '#FFFFFF', 0, 0],
        'banner' => ['Grand bandeau', '#080C21', '#FFC928', 490, 125],
        'title' => ['Enseigne', '#FFC928', '#101048', 0, 0],
        'menu' => ['Boutons du menu', '#FFC928', '#241359', 0, 0],
        'intro' => ['Ouverture', '#0B1546', '#FFFFFF', 420, 170],
        'news' => ['À l’antenne', '#FF8025', '#161616', 98, 135],
        'agenda' => ['Agenda', '#E1E7FF', '#101B48', 420, 130],
        'archive' => ['Archives', '#FF8025', '#161616', 98, 135],
        'focus' => ['Colonne latérale', '#FFC928', '#241359', 149, 130],
    ];
    public const BORDERS = [
        'solid' => 'Trait simple', 'double' => 'Double trait', 'ridge' => 'En relief',
        'inset' => 'En creux', 'outset' => 'Saillant', 'dashed' => 'Tirets', 'none' => 'Sans bordure',
    ];

    public static function defaults(): array
    {
        $styles = ['banner' => 'outset', 'title' => 'double', 'menu' => 'outset', 'intro' => 'double'];
        $defaults = [];
        foreach (self::ZONES as $key => [, $background, $text]) {
            $defaults[$key] = [
                'background' => $background, 'text' => $text, 'border' => $styles[$key] ?? 'solid',
                'image' => null, 'alt' => '',
            ];
        }
        return $defaults;
    }

    /** Validate thematic tokens without accepting CSS or HTML source. */
    public static function edit(array $posted, array $current, array $uploads): array
    {
        if (array_diff_key($posted, self::ZONES) || array_diff_key($uploads, self::ZONES)) {
            throw new InvalidArgumentException('La zone sélectionnée n’existe pas.');
        }
        $result = $current;
        foreach (self::ZONES as $key => [$label, , , $width]) {
            $input = $posted[$key] ?? null;
            if (!is_array($input)) {
                throw new InvalidArgumentException('Réglages incomplets pour « ' . $label . ' ».');
            }
            $bg = self::color($input['background'] ?? null);
            $text = self::color($input['text'] ?? null);
            $border = $input['border'] ?? null;
            if (!is_string($border) || !isset(self::BORDERS[$border])) {
                throw new InvalidArgumentException('Bordure inconnue pour « ' . $label . ' ».');
            }
            $a = self::luminance($bg);
            $b = self::luminance($text);
            if ((max($a, $b) + .05) / (min($a, $b) + .05) < 4.5) {
                throw new InvalidArgumentException('Augmente le contraste du texte pour « ' . $label . ' ».');
            }

            $image = ($input['remove'] ?? null) === '1' ? null : $current[$key]['image'];
            if (isset($uploads[$key])) {
                if ($width === 0) {
                    throw new InvalidArgumentException('Cette zone ne peut pas accueillir d’image.');
                }
                $image = $uploads[$key];
            }
            $alt = $input['alt'] ?? '';
            if (!is_string($alt) || !mb_check_encoding($alt, 'UTF-8') || mb_strlen($alt, 'UTF-8') > 200) {
                throw new InvalidArgumentException('Le texte de remplacement doit faire au plus 200 caractères.');
            }
            $result[$key] = ['background' => $bg, 'text' => $text, 'border' => $border,
                'image' => $image, 'alt' => $image === null ? '' : trim($alt)];
        }
        return $result;
    }

    private static function color(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^#[0-9a-f]{6}$/iD', $value) !== 1) {
            throw new InvalidArgumentException('Indique une couleur au format #RRGGBB.');
        }
        return strtoupper($value);
    }

    private static function luminance(string $hex): float
    {
        $channels = array_map(static function (string $value): float {
            $value = hexdec($value) / 255;
            return $value <= .04045 ? $value / 12.92 : (($value + .055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));
        return $channels[0] * .2126 + $channels[1] * .7152 + $channels[2] * .0722;
    }
}
