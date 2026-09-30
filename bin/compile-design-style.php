<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Design\CssTokenCompiler;
use LePotager\Cms\Design\DesignStyleDefinition;

$id = $argv[1] ?? null;
if (!is_string($id) || preg_match('/^[a-z][a-z0-9-]*$/', $id) !== 1) {
    fwrite(STDERR, "Usage: php bin/compile-design-style.php <style-id>\n");
    exit(2);
}
$path = dirname(__DIR__) . '/design-styles/' . $id . '/style.json';
if (!is_file($path)) { fwrite(STDERR, "Unknown design style: {$id}\n"); exit(3); }

try {
    $data = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Style JSON must decode to an object.');
    $style = new DesignStyleDefinition($data);
    echo (new CssTokenCompiler())->compile($style, '[data-design-style="' . $style->id() . '"]');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
