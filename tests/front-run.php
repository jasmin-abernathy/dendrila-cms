<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Content\ContentTypeDefinition;
use Dendrila\Cms\Front\PublicPageRenderer;
use Dendrila\Cms\Front\PublicPageSource;
use Dendrila\Cms\Privacy\FrontPageDocumentCollector;
use Dendrila\Cms\Privacy\PrivacyInspector;

function frontCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function frontExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        frontCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$fixture = json_decode(
    file_get_contents(dirname(__DIR__) . '/examples/design-reference-page.json'),
    true,
    64,
    JSON_THROW_ON_ERROR,
);
$pageType = new ContentTypeDefinition(json_decode(
    file_get_contents(dirname(__DIR__) . '/examples/content-types/page.json'),
    true,
    64,
    JSON_THROW_ON_ERROR,
));
$pageType->validateValues($fixture['page']);

$presentation = [
    'design_kit' => ['id' => 'simple-illustrated', 'version' => '0.2.1'],
    'compiled_css' => '[data-dendrila-theme="simple-illustrated"] { --dendrila-content-max: 68rem; }',
    'brand_assets' => [],
    'structure' => [
        'hero' => 'split',
        'alignment' => 'left',
        'gallery' => 'grid',
        'media_crop' => 'mixed',
        'full_bleed' => false,
        'accent_backgrounds' => 'soft_sections',
        'illustration_frequency' => 'occasional',
        'illustration_style' => 'line',
        'heading_style' => 'humanist',
        'body_style' => 'humanist',
    ],
    'media' => [
        'default_shape' => 'auto',
        'supported_shapes' => ['auto', 'organic-soft', 'organic-tall', 'arch', 'circle', 'rounded', 'none'],
        'focal_min' => 0,
        'focal_max' => 100,
    ],
];

$renderer = new PublicPageRenderer();
$html = $renderer->render(
    $fixture['site'],
    $fixture['page'],
    $presentation,
    $fixture['theme_css_url'],
    $fixture['media_urls'],
    $fixture['navigation'],
    '<div data-test-privacy="1"></div>',
);

frontCheck(str_contains($html, 'data-dendrila-theme="simple-illustrated"'), 'Public renderer selects the design kit.');
frontCheck(str_contains($html, 'data-dendrila-hero="split"'), 'Public renderer applies the hero structure token.');
frontCheck(str_contains($html, 'data-dendrila-gallery="grid"'), 'Public renderer applies the gallery token.');
frontCheck(str_contains($html, 'data-dendrila-heading-style="humanist"'), 'Public renderer exposes typography structure.');
frontCheck(str_contains($html, '--d-media-x:46%;--d-media-y:38%;'), 'Public renderer keeps non-destructive focal points.');
frontCheck(str_contains($html, 'data-media-shape="organic-tall"'), 'Public renderer keeps the media shape.');
frontCheck(str_contains($html, 'class="d-stats"'), 'Public renderer supports statistics.');
frontCheck(str_contains($html, 'class="d-key-values"'), 'Public renderer supports key/value blocks.');
frontCheck(str_contains($html, 'class="d-gallery"'), 'Public renderer supports galleries.');
frontCheck(str_contains($html, 'data-dendrila-src="https://www.youtube-nocookie.com/embed/abc123"'), 'External embeds are deferred through Dendrila Privacy.');
frontCheck(!str_contains($html, '<iframe src="https://www.youtube-nocookie.com/embed/abc123"'), 'External embeds never load before consent.');
frontCheck(str_contains($html, '&lt;strong&gt;aucun HTML arbitraire&lt;/strong&gt;'), 'Rich text is escaped instead of trusted as HTML.');
frontCheck(str_contains($html, 'data-test-privacy="1"'), 'Native privacy markup can be mounted by the front.');

$hostile = $fixture['page'];
$hostile['blocks'][] = ['type' => 'text', 'text' => '<script>alert(1)</script>'];
$hostileHtml = $renderer->render(
    $fixture['site'],
    $hostile,
    $presentation,
    $fixture['theme_css_url'],
    $fixture['media_urls'],
    $fixture['navigation'],
);
frontCheck(!str_contains($hostileHtml, '<script>alert(1)</script>'), 'User text cannot inject scripts.');
frontCheck(str_contains($hostileHtml, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Injected-looking text remains escaped.');

frontExpect(InvalidArgumentException::class, fn () => $renderer->render(
    $fixture['site'], $fixture['page'], $presentation, '//invalid.example/theme.css',
    $fixture['media_urls'], $fixture['navigation'],
));

$missingMedia = $fixture['media_urls'];
unset($missingMedia['hero']);
frontExpect(InvalidArgumentException::class, fn () => $renderer->render(
    $fixture['site'], $fixture['page'], $presentation, $fixture['theme_css_url'],
    $missingMedia, $fixture['navigation'],
));

$source = new class($fixture, $presentation) implements PublicPageSource {
    public function __construct(private array $fixture, private array $presentation) {}
    public function pages(ActorContext $actor, string $mode): array
    {
        return [[
            'id' => 'home',
            'url' => 'https://example.test/',
            'site' => $this->fixture['site'],
            'page' => $this->fixture['page'],
            'presentation' => $this->presentation,
            'theme_css_url' => $this->fixture['theme_css_url'],
            'media_urls' => $this->fixture['media_urls'],
            'navigation' => $this->fixture['navigation'],
        ]];
    }
};

$collector = new FrontPageDocumentCollector($renderer, $source);
$documents = $collector->collect(new ActorContext('site-a', 'editor-1'), 'standard');
frontCheck(count($documents) === 1 && str_contains($documents[0]['content'], 'data-dendrila-theme'), 'Privacy collector renders the public front.');
$services = array_column((new PrivacyInspector())->inspect($documents), 'service_id');
frontCheck(in_array('youtube', $services, true), 'Privacy inspection sees deferred external services in rendered HTML.');

echo "Public front renderer tests passed.\n";
