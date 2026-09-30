<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/Design/DesignStyleDefinition.php';
require dirname(__DIR__) . '/src/Design/CssTokenCompiler.php';

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
    $css = $compiler->compile($style, '[data-design-style="' . $style->id() . '"]');
    designCheck(str_contains($css, '--mmw-content-max:'), 'Compiled style exposes layout tokens.');
    designCheck(str_contains($css, 'prefers-reduced-motion'), 'Compiled style keeps reduced-motion protection.');
    designCheck(!str_contains(strtolower($css), 'javascript:'), 'Compiled CSS never contains executable URLs.');
}
echo 'Design style tests passed (' . count($ids) . " presets).\n";
