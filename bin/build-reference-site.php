#!/usr/bin/env php
<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Content\ContentTypeDefinition;
use Dendrila\Cms\Design\DesignKitCatalog;
use Dendrila\Cms\Design\PresentationResolver;
use Dendrila\Cms\Design\SiteCompositionDefinition;
use Dendrila\Cms\Front\ReferenceSiteBuilder;

$catalogPath = $argv[1] ?? getenv('DENDRILA_DESIGN_KITS_CATALOG') ?: null;
$outputDirectory = $argv[2] ?? dirname(__DIR__) . '/build/reference-site';
$enableRipple = ($argv[3] ?? null) === '--ripple';

if (!is_string($catalogPath) || $catalogPath === '') {
    fwrite(STDERR, "Usage: php bin/build-reference-site.php <design-kits/catalog.json> [output-directory] [--ripple]\n");
    exit(2);
}

try {
    $root = dirname(__DIR__);
    $compositionRaw = json_decode(
        (string) file_get_contents($root . '/examples/site-composition.json'),
        true,
        64,
        JSON_THROW_ON_ERROR,
    );
    $fixture = json_decode(
        (string) file_get_contents($root . '/examples/design-reference-page.json'),
        true,
        64,
        JSON_THROW_ON_ERROR,
    );
    $pageType = json_decode(
        (string) file_get_contents($root . '/examples/content-types/page.json'),
        true,
        64,
        JSON_THROW_ON_ERROR,
    );

    if (!is_array($compositionRaw) || !is_array($fixture) || !is_array($pageType)) {
        throw new RuntimeException('Reference site input files must be JSON objects.');
    }

    (new ContentTypeDefinition($pageType))->validateValues($fixture['page'] ?? []);
    if ($enableRipple) {
        // Demo opt-in only: no site settings, content, schemas or production database are changed.
        $fixture['media_urls']['ripple-demo'] = '/media/ripple-retro.svg';
        $fixture['backdrop'] = [
            'type' => 'ripple', 'media_id' => 'ripple-demo',
            'speed' => 0.85, 'intensity' => 0.75,
        ];
    }
    $builder = new ReferenceSiteBuilder(
        new PresentationResolver($root . '/design-styles', new DesignKitCatalog($catalogPath)),
    );
    $result = $builder->build(
        new SiteCompositionDefinition($compositionRaw),
        $fixture,
        $root . '/examples/reference-media',
        (string) $outputDirectory,
    );

    fwrite(STDOUT, "Reference site built: {$result['index_path']}\n");
    if ($enableRipple) {
        fwrite(STDOUT, "Ripple demo: {$result['output_directory']}/ripple-preview.html\n");
    }
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
