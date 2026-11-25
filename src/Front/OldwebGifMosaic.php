<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use InvalidArgumentException;

final class OldwebGifMosaic
{
    private const BREAKPOINTS = [3 => 600, 4 => 760, 5 => 920, 6 => 1080, 7 => 1280, 8 => 1440];
    private const ROWS = 16;

    /**
     * The host supplies metadata from the site's public media library.
     * IDs from the editor cannot provide paths or dimensions.
     *
     * @return list<array{url:string,width:int,height:int}>
     */
    public static function resolve(mixed $ids, array $publicMedia, array $gifMetadata): array
    {
        if (!is_array($ids) || !array_is_list($ids) || count($ids) < 1 || count($ids) > 10) {
            throw new InvalidArgumentException('Choisis entre 1 et 10 GIF pour la mosaïque.');
        }

        $tiles = [];
        foreach ($ids as $id) {
            if (!is_string($id) || preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $id) !== 1 || isset($tiles[$id])) {
                throw new InvalidArgumentException('Chaque GIF doit être sélectionné une seule fois.');
            }
            $dimensions = $gifMetadata[$id] ?? [];
            $url = $publicMedia[$id] ?? null;
            $width = $dimensions['width'] ?? null;
            $height = $dimensions['height'] ?? null;
            if (!is_array($dimensions) || !is_string($url) || !self::localGif($url) || ($dimensions['mime'] ?? null) !== 'image/gif'
                || !self::validSide($width) || !self::validSide($height)) {
                throw new InvalidArgumentException('Choisis des GIF publics de la médiathèque avec des dimensions valides.');
            }
            $tiles[$id] = ['url' => $url, 'width' => $width, 'height' => $height];
        }

        return array_values($tiles);
    }

    /** @return array{head:string,markup:string} */
    public function render(array $tiles, string $language): array
    {
        if (!array_is_list($tiles) || count($tiles) < 1 || count($tiles) > 10) {
            throw new InvalidArgumentException('Invalid Oldweb mosaic.');
        }
        foreach ($tiles as $tile) {
            if (!is_array($tile) || !is_string($tile['url'] ?? null) || !self::localGif($tile['url'])
                || !self::validSide($tile['width'] ?? null) || !self::validSide($tile['height'] ?? null)) {
                throw new InvalidArgumentException('Invalid Oldweb mosaic tile.');
            }
        }

        $head = '<style type="text/css">'
            . '.d-mosaic-page{position:relative;z-index:0}.d-mosaic{position:fixed;top:0;right:0;bottom:0;left:0;z-index:-1;overflow:hidden}'
            . '.d-mosaic-variant{display:none}.d-mosaic-2{display:block}'
            . '.d-mosaic-row{overflow:hidden;white-space:nowrap;font-size:0;line-height:0}'
            . '.d-mosaic-row img{display:inline-block;vertical-align:top;height:auto;border:0}'
            . '.d-mosaic-controls{position:relative;z-index:1;padding:.4em .7em;background:#fff;color:#111;width:auto}'
            . '.d-mosaic-controls a{color:#000080}.d-mosaic-on{display:none}'
            . '#d-mosaic-off:target .d-mosaic-on{display:inline}#d-mosaic-off:target .d-mosaic-off{display:none}'
            . '#d-mosaic-off:target + .d-mosaic{display:none}';
        foreach (self::BREAKPOINTS as $columns => $minWidth) {
            $previous = $columns - 1;
            $head .= "@media screen and (min-width:{$minWidth}px){.d-mosaic-{$previous}{display:none}.d-mosaic-{$columns}{display:block}}";
        }

        $hide = $language === 'en' ? 'Hide patterned background' : 'Masquer le fond en mosaïque';
        $show = $language === 'en' ? 'Show patterned background' : 'Afficher le fond en mosaïque';
        $markup = '<div class="d-mosaic-controls" id="d-mosaic-off">'
            . '<a class="d-mosaic-off" href="#d-mosaic-off">' . $hide . '</a>'
            . '<a class="d-mosaic-on" href="#d-mosaic-on"> ' . $show . '</a></div>'
            . '<div class="d-mosaic" style="background:#ddd url(' . $tiles[0]['url'] . ') repeat">';
        for ($columns = 2; $columns <= 8; $columns++) {
            $markup .= '<div class="d-mosaic-variant d-mosaic-' . $columns . '">';
            $previous = [];
            for ($index = 0; $index < self::ROWS; $index++) {
                $row = $this->drawRow($tiles, $columns, $previous);
                $markup .= $this->renderRow($row);
                $previous = $row;
            }
            $markup .= '</div>';
        }

        return ['head' => $head . '</style>', 'markup' => $markup . '</div>'];
    }

    private function drawRow(array $tiles, int $columns, array $previous): array
    {
        $row = [];
        do {
            $pool = $tiles;
            shuffle($pool);
            array_push($row, ...array_slice($pool, 0, $columns - count($row)));
        } while (count($row) < $columns);
        if ($row === $previous && count($tiles) > 1) {
            [$row[0], $row[1]] = [$row[1], $row[0]];
        }

        return $row;
    }

    private function renderRow(array $row): string
    {
        $height = min(array_column($row, 'height'));
        $sum = array_sum(array_map(static fn (array $tile): float => $tile['width'] / $tile['height'], $row));
        $markup = '<div class="d-mosaic-row" style="max-height:' . $height . 'px">';
        foreach ($row as $tile) {
            // The aspect ratio sets each slot's share. All images scale to the same height.
            // The extra 0.1% avoids gaps from fractional pixel rounding at the right edge.
            $width = 100.1 * ($tile['width'] / $tile['height']) / $sum;
            $markup .= '<img src="' . $tile['url'] . '" alt="" width="' . $tile['width'] . '" height="' . $tile['height']
                . '" style="width:' . number_format($width, 6, '.', '') . '%">';
        }

        return $markup . '</div>';
    }

    private static function validSide(mixed $value): bool
    {
        return is_int($value) && $value > 0 && $value <= 4096;
    }

    private static function localGif(string $url): bool
    {
        return preg_match('~^/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_.-]+\.gif$~iD', $url) === 1
            && !str_contains($url, '..');
    }
}
