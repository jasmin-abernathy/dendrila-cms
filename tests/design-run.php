<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Design/DesignStyleDefinition.php';
require dirname(__DIR__) . '/src/Design/CssTokenCompiler.php';
require dirname(__DIR__) . '/src/Design/SiteCompositionDefinition.php';
require dirname(__DIR__) . '/src/Design/BrandTokenCompiler.php';
require dirname(__DIR__) . '/src/Design/DesignKitCatalog.php';
require dirname(__DIR__) . '/src/Design/PresentationResolver.php';

use Dendrila\Cms\Design\CssTokenCompiler;
use Dendrila\Cms\Design\DesignStyleDefinition;
use Dendrila\Cms\Design\DesignKitCatalog;
use Dendrila\Cms\Design\PresentationResolver;
use Dendrila\Cms\Design\SiteCompositionDefinition;

function designCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function designExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        designCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$files = glob(dirname(__DIR__) . '/design-styles/*/style.json') ?: [];
designCheck(count($files) >= 6, 'Expected at least six initial visual styles.');
$ids = [];
$compiler = new CssTokenCompiler();
foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
    designCheck(is_array($data), 'Each design style must be a JSON object.');
    $style = new DesignStyleDefinition($data);
    designCheck(!isset($ids[$style->id()]), 'Design style identifiers must be unique.');
    $ids[$style->id()] = true;
    $css = $compiler->compile($style, '[data-design-style="' . $style->id() . '"]');
    designCheck(str_contains($css, '--dendrila-content-max:'), 'Compiled style exposes layout tokens.');
    designCheck(str_contains($css, 'prefers-reduced-motion'), 'Compiled style keeps reduced-motion protection.');
    designCheck(!str_contains(strtolower($css), 'javascript:'), 'Compiled CSS never contains executable URLs.');
}

$simpleStylePath = dirname(__DIR__) . '/design-styles/simple-illustrated/style.json';
$simpleStyleData = json_decode(file_get_contents($simpleStylePath), true, 64, JSON_THROW_ON_ERROR);
$simpleStyle = new DesignStyleDefinition($simpleStyleData);
$requiredTokens = array_keys($compiler->variables($simpleStyle));

$temp = sys_get_temp_dir() . '/dendrila-design-' . bin2hex(random_bytes(6));
$themeRoot = $temp . '/themes/simple-illustrated';
mkdir($themeRoot, 0777, true);
file_put_contents($themeRoot . '/theme.css', '/* test theme */');
file_put_contents($themeRoot . '/theme.json', json_encode([
    'schema_version' => 1,
    'id' => 'simple-illustrated',
    'design_style_id' => 'simple-illustrated',
    'version' => '0.1.1',
    'entrypoint' => 'theme.css',
    'components' => ['header', 'hero-split', 'media'],
    'media_shapes' => ['auto', 'organic-soft', 'rounded', 'none'],
    'brand_tokens' => ['--dendrila-color-primary', '--dendrila-font-heading'],
    'required_style_tokens' => $requiredTokens,
    'accessibility' => [
        'focus_visible' => true,
        'reduced_motion' => true,
        'no_autoplay_media' => true,
    ],
], JSON_THROW_ON_ERROR));
$catalogPath = $temp . '/catalog.json';
file_put_contents($catalogPath, json_encode([
    'schema_version' => 1,
    'themes' => [[
        'id' => 'simple-illustrated',
        'design_style_id' => 'simple-illustrated',
        'version' => '0.1.1',
        'path' => 'themes/simple-illustrated',
        'manifest' => 'theme.json',
    ]],
], JSON_THROW_ON_ERROR));

$composition = new SiteCompositionDefinition([
    'schema_version' => 1,
    'functional_kit' => 'association',
    'design_style' => 'simple-illustrated',
    'enabled_modules' => ['content', 'media'],
    'brand_overrides' => [
        'primary' => '#4B6857',
        'background' => '#F8FAF8',
        'heading_font' => 'editorial-serif',
        'body_font' => 'humanist-sans',
        'logo_url' => 'https://example.test/logo.svg',
    ],
]);
$presentation = (new PresentationResolver(
    dirname(__DIR__) . '/design-styles',
    new DesignKitCatalog($catalogPath),
))->resolve($composition);
designCheck($presentation['design_kit']['id'] === 'simple-illustrated', 'Presentation resolves the installed design kit.');
designCheck(is_file($presentation['design_kit']['entrypoint_path']), 'Presentation exposes a real local theme entrypoint.');
designCheck($presentation['css_variables']['--dendrila-content-max'] === '68rem', 'Presentation carries compiled style tokens.');
designCheck($presentation['css_variables']['--dendrila-color-primary'] === '#4B6857', 'Presentation carries safe brand colors.');
designCheck(str_contains($presentation['compiled_css'], '--dendrila-font-heading: Georgia'), 'Presentation maps font choices to controlled stacks.');
designCheck($presentation['media']['supported_shapes'][1] === 'organic-soft', 'Presentation exposes theme media capabilities.');
designCheck($presentation['structure']['hero'] === $simpleStyle->token('layout.hero'), 'Presentation exposes the hero structure token.');
designCheck($presentation['structure']['gallery'] === $simpleStyle->token('media.gallery'), 'Presentation exposes the gallery structure token.');
designCheck($presentation['structure']['full_bleed'] === $simpleStyle->token('media.full_bleed'), 'Presentation exposes the full-bleed token.');
designCheck($presentation['structure']['illustration_frequency'] === $simpleStyle->token('illustration.frequency'), 'Presentation exposes illustration frequency.');
designCheck($presentation['brand_assets']['logo_url'] === 'https://example.test/logo.svg', 'Presentation keeps safe brand asset URLs separate from CSS.');

designExpect(InvalidArgumentException::class, fn () => new SiteCompositionDefinition([
    'schema_version' => 1,
    'functional_kit' => 'association',
    'design_style' => 'simple-illustrated',
    'brand_overrides' => ['primary' => 'red; background:url(javascript:alert(1))'],
]));
designExpect(InvalidArgumentException::class, fn () => new SiteCompositionDefinition([
    'schema_version' => 1,
    'functional_kit' => 'association',
    'design_style' => 'simple-illustrated',
    'brand_overrides' => ['logo_url' => 'http://example.test/logo.svg'],
]));

$manifest = json_decode(file_get_contents($themeRoot . '/theme.json'), true, 64, JSON_THROW_ON_ERROR);
$manifest['required_style_tokens'][] = '--dendrila-unknown-token';
file_put_contents($themeRoot . '/theme.json', json_encode($manifest, JSON_THROW_ON_ERROR));
designExpect(RuntimeException::class, fn () => (new PresentationResolver(
    dirname(__DIR__) . '/design-styles',
    new DesignKitCatalog($catalogPath),
))->resolve($composition));

file_put_contents($temp . '/unsafe-catalog.json', json_encode([
    'schema_version' => 1,
    'themes' => [[
        'id' => 'unsafe',
        'design_style_id' => 'simple-illustrated',
        'version' => '0.1.0',
        'path' => '../outside',
        'manifest' => 'theme.json',
    ]],
], JSON_THROW_ON_ERROR));
designExpect(InvalidArgumentException::class, fn () => new DesignKitCatalog($temp . '/unsafe-catalog.json'));

@unlink($themeRoot . '/theme.css');
@unlink($themeRoot . '/theme.json');
@rmdir($themeRoot);
@rmdir($temp . '/themes');
@unlink($catalogPath);
@unlink($temp . '/unsafe-catalog.json');
@rmdir($temp);

echo 'Design style and design kit tests passed (' . count($ids) . " presets).\n";
