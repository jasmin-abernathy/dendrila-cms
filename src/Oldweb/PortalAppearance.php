<?php
declare(strict_types=1);

namespace Dendrila\Cms\Oldweb;

use InvalidArgumentException;

final class PortalAppearance
{
    public const ZONES = [
        'page' => ['Fond du site', '#2455E5', '#FFFFFF', 1600, 1600],
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

    public const FONTS = [
        'theme' => ['Police du thème', ''],
        'arial' => ['Arial', 'Arial, "Liberation Sans", Helvetica, sans-serif'],
        'arial-black' => ['Arial Black', '"Arial Black", Arial, "Liberation Sans", sans-serif'],
        'tahoma' => ['Tahoma', 'Tahoma, "Segoe UI", Arial, sans-serif'],
        'verdana' => ['Verdana', 'Verdana, "DejaVu Sans", Arial, sans-serif'],
        'times' => ['Times New Roman', '"Times New Roman", "Liberation Serif", Times, serif'],
        'courier' => ['Courier New', '"Courier New", "Liberation Mono", Courier, monospace'],
        'lucida-console' => ['Lucida Console', '"Lucida Console", Monaco, "DejaVu Sans Mono", monospace'],
        'comic-sans' => ['Comic Sans MS', '"Comic Sans MS", "Comic Neue", cursive'],
        'impact' => ['Impact', 'Impact, "Arial Narrow", "Liberation Sans Narrow", sans-serif'],
    ];

    public static function defaults(): array
    {
        $styles = ['banner' => 'outset', 'title' => 'double', 'menu' => 'outset', 'intro' => 'double'];
        $defaults = [];
        foreach (self::ZONES as $key => [, $background, $text]) {
            $defaults[$key] = [
                'background' => $background, 'text' => $text, 'border' => $styles[$key] ?? 'solid',
                'image' => null, 'alt' => '', 'font' => 'theme',
            ];
        }
        $defaults['page']['mode'] = 'theme';
        return $defaults;
    }

    /** Validate thematic tokens without accepting CSS or HTML source. */
    public static function edit(array $posted, array $current, array $uploads, string $language = PortalLocale::DEFAULT): array
    {
        if (array_diff_key($posted, self::ZONES) || array_diff_key($uploads, self::ZONES)) {
            throw new InvalidArgumentException('La zone sélectionnée n’existe pas.');
        }
        $result = $current;
        foreach (self::ZONES as $key => [$label, , , $width]) {
            $input = $posted[$key] ?? null;
            if (!is_array($input)) {
                throw new InvalidArgumentException(PortalLocale::text(
                    $language, 'Réglages incomplets pour « %s ».', PortalLocale::text($language, $label)
                ));
            }
            $bg = self::color($input['background'] ?? null);
            $text = self::color($input['text'] ?? null);
            $border = $input['border'] ?? null;
            if (!is_string($border) || !isset(self::BORDERS[$border])) {
                throw new InvalidArgumentException(PortalLocale::text(
                    $language, 'Bordure inconnue pour « %s ».', PortalLocale::text($language, $label)
                ));
            }
            $font = $input['font'] ?? $current[$key]['font'] ?? 'theme';
            if (!is_string($font) || !isset(self::FONTS[$font])) {
                throw new InvalidArgumentException(PortalLocale::text(
                    $language, 'Police inconnue pour « %s ».', PortalLocale::text($language, $label)
                ));
            }
            $a = self::luminance($bg);
            $b = self::luminance($text);
            if ((max($a, $b) + .05) / (min($a, $b) + .05) < 4.5) {
                throw new InvalidArgumentException(PortalLocale::text(
                    $language, 'Augmente le contraste du texte pour « %s ».', PortalLocale::text($language, $label)
                ));
            }

            $image = ($input['remove'] ?? null) === '1' ? null : $current[$key]['image'];
            if (isset($uploads[$key])) {
                if ($width === 0) {
                    throw new InvalidArgumentException('Cette zone ne peut pas accueillir d’image.');
                }
                $image = $uploads[$key];
            }
            $alt = $key === 'page' ? '' : ($input['alt'] ?? '');
            if (!is_string($alt) || !mb_check_encoding($alt, 'UTF-8') || mb_strlen($alt, 'UTF-8') > 200) {
                throw new InvalidArgumentException('Le texte de remplacement doit faire au plus 200 caractères.');
            }
            $result[$key] = ['background' => $bg, 'text' => $text, 'border' => $border,
                'image' => $image, 'alt' => $image === null ? '' : trim($alt), 'font' => $font];
            if ($key === 'page') {
                $mode = $input['mode'] ?? $current[$key]['mode'] ?? 'theme';
                if (!in_array($mode, ['theme', 'color', 'image'], true)) {
                    throw new InvalidArgumentException('Choisis un type de fond valide.');
                }
                if (($input['remove'] ?? null) === '1' && !isset($uploads[$key]) && $mode === 'image') $mode = 'theme';
                if ($mode === 'image' && $image === null) throw new InvalidArgumentException('Choisis une image de fond.');
                $result[$key]['mode'] = $mode;
            }
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
