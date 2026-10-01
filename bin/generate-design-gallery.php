<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Design\CssTokenCompiler;
use LePotager\Cms\Design\DesignStyleDefinition;
use LePotager\Cms\Design\FrontStyleAdapter;

$root = dirname(__DIR__);
$directory = $root . '/examples/design-gallery';
$files = glob($root . '/design-styles/*/style.json') ?: [];
if ($files === []) throw new RuntimeException('No installed styles.');
$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$header = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Styles visuels — Dendrila CMS</title><link rel="stylesheet" href="styles.css"></head><body><main><h1>Quel résultat graphique souhaitez-vous ?</h1><p class="intro">Comparez six styles sur le même contenu. Votre structure, vos fonctions et votre identité restent des choix distincts.</p><div class="grid">';
$demo = file_get_contents($directory . '/demo.html');
$css = file_get_contents($directory . '/gallery.css');
$html = $header;
foreach ($files as $file) {
    $data = json_decode(file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
    $style = new DesignStyleDefinition($data);
    $id = $style->id();
    $html .= '<section class="preview" aria-labelledby="label-' . $id . '"><header class="style-label"><h2 id="label-' . $id . '">' . $escape($style->label()) . '</h2><p>' . $escape($data['description']) . '</p></header><div class="mmw-site" ' . (new FrontStyleAdapter())->htmlAttributes($style) . '>' . str_replace('ID', $id, trim($demo)) . '</div></section>';
    $css .= (new CssTokenCompiler())->compile($style, '[data-design-style="' . $id . '"]');
}
$html .= "</div></main></body></html>\n";
if (file_put_contents($directory . '/index.html', $html) === false
    || file_put_contents($directory . '/styles.css', $css) === false) {
    throw new RuntimeException('Cannot write gallery assets.');
}
echo "Gallery generated from installed style catalog.\n";
