<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Design\CssTokenCompiler;
use Dendrila\Cms\Design\DesignKitCatalog;
use Dendrila\Cms\Design\DesignStyleDefinition;
use Dendrila\Cms\Design\PresentationResolver;
use Dendrila\Cms\Design\SiteCompositionDefinition;
use Dendrila\Cms\Front\ReferenceSiteBuilder;

function referenceCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function referenceExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        referenceCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

function referenceRemoveTree(string $path): void
{
    if (!is_dir($path)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

$root = dirname(__DIR__);
$styleData = json_decode(
    file_get_contents($root . '/design-styles/simple-illustrated/style.json'),
    true,
    64,
    JSON_THROW_ON_ERROR,
);
$style = new DesignStyleDefinition($styleData);
$requiredTokens = array_keys((new CssTokenCompiler())->variables($style));

$temp = sys_get_temp_dir() . '/dendrila-reference-' . bin2hex(random_bytes(6));
$themeRoot = $temp . '/kits/themes/simple-illustrated';
mkdir($themeRoot . '/assets/masks', 0777, true);
file_put_contents($themeRoot . '/theme.css', '.d-shell{max-width:68rem}');
file_put_contents($themeRoot . '/assets/masks/organic-soft.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>');
file_put_contents($themeRoot . '/theme.json', json_encode([
    'schema_version' => 1,
    'id' => 'simple-illustrated',
    'design_style_id' => 'simple-illustrated',
    'version' => '0.2.1',
    'entrypoint' => 'theme.css',
    'components' => ['header', 'hero-split', 'media', 'gallery', 'statistics'],
    'media_shapes' => ['auto', 'organic-soft', 'organic-tall', 'arch', 'circle', 'rounded', 'none'],
    'brand_tokens' => ['--dendrila-color-primary', '--dendrila-font-heading'],
    'required_style_tokens' => $requiredTokens,
    'accessibility' => [
        'focus_visible' => true,
        'reduced_motion' => true,
        'no_autoplay_media' => true,
    ],
], JSON_THROW_ON_ERROR));

$catalogPath = $temp . '/kits/catalog.json';
file_put_contents($catalogPath, json_encode([
    'schema_version' => 1,
    'themes' => [[
        'id' => 'simple-illustrated',
        'design_style_id' => 'simple-illustrated',
        'version' => '0.2.1',
        'path' => 'themes/simple-illustrated',
        'manifest' => 'theme.json',
    ]],
], JSON_THROW_ON_ERROR));

$compositionRaw = json_decode(
    file_get_contents($root . '/examples/site-composition.json'),
    true,
    64,
    JSON_THROW_ON_ERROR,
);
$fixture = json_decode(
    file_get_contents($root . '/examples/design-reference-page.json'),
    true,
    64,
    JSON_THROW_ON_ERROR,
);

$builder = new ReferenceSiteBuilder(
    new PresentationResolver($root . '/design-styles', new DesignKitCatalog($catalogPath)),
);
$output = $temp . '/build';
$result = $builder->build(
    new SiteCompositionDefinition($compositionRaw),
    $fixture,
    $root . '/examples/reference-media',
    $output,
);

referenceCheck(is_file($result['index_path']), 'Reference builder writes index.html.');
referenceCheck(is_file($output . '/theme/theme.css'), 'Reference builder copies the installed theme.');
referenceCheck(is_file($output . '/theme/assets/masks/organic-soft.svg'), 'Reference builder copies theme assets.');
referenceCheck(is_file($output . '/media/reference.svg'), 'Reference builder copies neutral reference media.');
referenceCheck(is_file($output . '/README.txt'), 'Reference builder writes local serving instructions.');

$html = file_get_contents($result['index_path']);
referenceCheck(str_contains($html, 'href="/theme/theme.css"'), 'Built page references the copied design kit.');
referenceCheck(str_contains($html, 'src="/media/reference.svg"'), 'Built page references copied local media.');
referenceCheck(str_contains($html, 'data-dendrila-theme="simple-illustrated"'), 'Built page applies the selected theme.');
referenceCheck(str_contains($html, 'data-media-shape="organic-tall"'), 'Built page keeps media shape metadata.');
referenceCheck(str_contains($html, '--d-media-x:46%;--d-media-y:38%;'), 'Built page keeps focal point metadata.');

referenceExpect(InvalidArgumentException::class, fn () => $builder->build(
    new SiteCompositionDefinition($compositionRaw),
    $fixture,
    $root . '/examples/reference-media',
    $output,
));

referenceRemoveTree($temp);

echo "Reference site builder tests passed.\n";
