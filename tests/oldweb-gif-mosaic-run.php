<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Front\OldwebGifMosaic;

function mosaicCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
function mosaicReject(callable $validate): void
{
    try {
        $validate();
    } catch (InvalidArgumentException) {
        return;
    }
    throw new RuntimeException('Invalid GIF selection was accepted.');
}

$media = $metadata = [];
for ($index = 0; $index <= 10; $index++) {
    $id = 'tile-' . $index;
    $media[$id] = '/media/' . $id . '.gif';
    $metadata[$id] = ['mime' => 'image/gif', 'width' => 120, 'height' => 50 + $index * 10];
}
$ids = array_keys($media);
mosaicReject(fn () => OldwebGifMosaic::resolve([], $media, $metadata));
mosaicReject(fn () => OldwebGifMosaic::resolve($ids, $media, $metadata));
mosaicReject(fn () => OldwebGifMosaic::resolve([$ids[0], $ids[0]], $media, $metadata));
mosaicReject(fn () => OldwebGifMosaic::resolve(['unknown'], $media, $metadata));
mosaicReject(fn () => OldwebGifMosaic::resolve([$ids[0]], $media, []));
mosaicReject(fn () => OldwebGifMosaic::resolve([$ids[0]], [$ids[0] => '//example.test/x.gif'], $metadata));

$mosaic = new OldwebGifMosaic();
$single = $mosaic->render(OldwebGifMosaic::resolve([$ids[0]], $media, $metadata), 'fr');
mosaicCheck(substr_count($single['markup'], '<img ') === 16 * array_sum(range(2, 8)), 'A single GIF must fill every slot.');
mosaicCheck(!str_contains($single['markup'], '<script'), 'Mosaic should render without JavaScript.');
mosaicCheck(str_contains($single['head'], '(min-width:1440px)'), 'Eight-tile breakpoint is missing.');

$tiles = OldwebGifMosaic::resolve(array_slice($ids, 0, 10), $media, $metadata);
mosaicCheck(count($tiles) === 10, 'Ten GIFs should be accepted.');
$pair = $mosaic->render(array_slice($tiles, 0, 2), 'fr');
preg_match_all('~<div class="d-mosaic-row" style="max-height:50px">(.+?)</div>~', $pair['markup'], $rows);
mosaicCheck(count($rows[1]) === 16 * count(range(2, 8)), 'Each row must use the shortest image in that row.');
mosaicCheck($rows[1][0] !== $rows[1][1], 'Successive two-slot rows should not use the same order.');

echo "Oldweb GIF mosaic tests passed.\n";
