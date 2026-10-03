#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Design\DesignKitCatalog;
use Dendrila\Cms\Design\PresentationResolver;
use Dendrila\Cms\Design\SiteCompositionDefinition;

$compositionPath = $argv[1] ?? null;
$catalogPath = $argv[2] ?? getenv('DENDRILA_DESIGN_KITS_CATALOG') ?: null;
if (!is_string($compositionPath) || $compositionPath === '' || !is_string($catalogPath) || $catalogPath === '') {
    fwrite(STDERR, "Usage: php bin/resolve-site-presentation.php <site-composition.json> [design-kits/catalog.json]\n");
    exit(2);
}

try {
    $raw = json_decode((string) file_get_contents($compositionPath), true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($raw)) {
        throw new InvalidArgumentException('Site composition must be a JSON object.');
    }
    $composition = new SiteCompositionDefinition($raw);
    $resolver = new PresentationResolver(dirname(__DIR__) . '/design-styles', new DesignKitCatalog($catalogPath));
    echo json_encode($resolver->resolve($composition), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
