<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Design/DesignStyleDefinition.php';
require dirname(__DIR__) . '/src/Design/CssTokenCompiler.php';

require dirname(__DIR__) . '/src/Design/FrontStyleAdapter.php';
require dirname(__DIR__) . '/src/Design/SiteComposition.php';

use LePotager\Cms\Design\FrontStyleAdapter;
use LePotager\Cms\Design\SiteComposition;
use LePotager\Cms\Design\CssTokenCompiler;
use LePotager\Cms\Design\DesignStyleDefinition;

function designCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
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
    $attributes = (new FrontStyleAdapter())->attributes($style);
    designCheck(count($attributes) === 11, 'Front adapter exposes all structural tokens.');
    designCheck($attributes['data-hero'] === $style->token('layout.hero'), 'Hero comes from the preset.');
    $composition = new SiteComposition([
        'schema_version'=>1, 'functional_kit'=>'generic', 'design_style'=>$style->id(),
        'enabled_modules'=>['news'], 'style_overrides'=>['layout'=>['alignment'=>'center']],
    ], [$style->id()=>$style], ['generic'], ['news']);
    designCheck($composition->style->token('layout.alignment') === 'center', 'Bounded overrides are applied.');
    foreach ([
        ['style_overrides'=>['layout'=>['css'=>'color:red']]],
        ['style_overrides'=>['layout'=>['alignment'=>'left; color:red']]],
        ['enabled_modules'=>['unknown']],
        ['enabled_modules'=>['news','news']],
        ['brand_overrides'=>['logo_url'=>'javascript:alert(1)']],
        ['brand_overrides'=>['text'=>'#ffffff','background'=>'#ffffff']],
        ['css'=>'body{}'],
    ] as $bad) {
        $rejected = false;
        try {
            new SiteComposition(array_replace($composition->toArray(), $bad), [$style->id()=>$style], ['generic'], ['news']);
        } catch (InvalidArgumentException) { $rejected = true; }
        designCheck($rejected, 'Unsafe composition must be rejected.');
    }
    $emptyObjects = new SiteComposition([
        'schema_version'=>1,'functional_kit'=>'generic','design_style'=>$style->id(),
        'brand_overrides'=>[], 'style_overrides'=>[],
    ], [$style->id()=>$style], ['generic'], []);
    designCheck(str_contains($emptyObjects->json(), '"brand_overrides":{}'), 'Empty brand object stays an object in JSON.');
    designCheck(str_contains($emptyObjects->json(), '"style_overrides":{}'), 'Empty override object stays an object in JSON.');
    $css = $compiler->compile($style, '[data-design-style="' . $style->id() . '"]');
    designCheck(str_contains(file_get_contents(dirname(__DIR__) . '/examples/design-gallery/styles.css'), $css), 'Gallery variables match the compiler; regenerate the gallery after preset changes.');
    designCheck(str_contains(file_get_contents(dirname(__DIR__) . '/examples/design-gallery/index.html'), (new FrontStyleAdapter())->htmlAttributes($style)), 'Gallery attributes match the adapter.');
    designCheck(str_contains($css, '--mmw-content-max:'), 'Compiled style exposes layout tokens.');
    designCheck(str_contains($css, 'prefers-reduced-motion'), 'Compiled style keeps reduced-motion protection.');
    designCheck(!str_contains(strtolower($css), 'javascript:'), 'Compiled CSS never contains executable URLs.');
}
echo 'Design style tests passed (' . count($ids) . " presets).\n";
