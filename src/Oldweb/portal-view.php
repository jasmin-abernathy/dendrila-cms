<?php
declare(strict_types=1);

/** @var array $appearance */
/** @var array $content */
/** @var bool $preview */
/** @var bool $still */
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$format = static function (string $zone) use ($appearance): string {
    $style = $appearance[$zone];
    return 'background-color:' . $style['background'] . ';color:' . $style['text'] . ';border-style:' . $style['border'];
};
$image = static function (string $zone) use ($appearance, $escape, $preview, $still): string {
    $file = $appearance[$zone]['image'];
    if ($file === null || ($still && str_ends_with($file, '.gif'))) {
        return '';
    }
    return '<img class="illustration" src="?media=' . $escape($file) . ($preview ? '&amp;preview=1' : '')
        . '" alt="' . $escape($appearance[$zone]['alt']) . '">';
};
$hasGif = count(array_filter($appearance, static fn (array $zone): bool =>
    is_string($zone['image']) && str_ends_with($zone['image'], '.gif'))) > 0;
$t = static fn (string $key): string => $escape($content[$key]);

$backgroundMode = $appearance['page']['mode'] ?? 'theme';
$backgroundUrl = null;
if ($backgroundMode === 'image') {
    $file = $appearance['page']['image'];
    if (is_string($file) && preg_match('/^[a-f0-9]{64}\\.(gif|png|jpg)$/D', $file) && !($still && str_ends_with($file, '.gif'))) {
        $backgroundUrl = '?media=' . $file . ($preview ? '&preview=1' : '');
    }
}
$backgroundCss = $backgroundMode === 'theme' ? '' : 'background-color:' . $appearance['page']['background']
    . ';background-image:' . ($backgroundUrl === null ? 'none' : 'url("' . $backgroundUrl . '")') . ';background-repeat:repeat;';
