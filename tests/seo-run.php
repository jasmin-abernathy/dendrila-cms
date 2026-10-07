<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Seo\SeoHeadRenderer;
use Dendrila\Cms\Seo\SeoMetadataDefinition;
use Dendrila\Cms\Seo\SeoRepository;
use Dendrila\Cms\Seo\SeoService;
use Dendrila\Cms\Seo\SitemapBuilder;
use Dendrila\Cms\Tests\Support\InMemoryCmsStore;

function seoCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function seoExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        seoCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$repository = new class implements SeoRepository {
    public array $records = [];

    public function find(string $siteId, string $contentId): ?array
    {
        return $this->records[$siteId][$contentId] ?? null;
    }

    public function findForUpdate(string $siteId, string $contentId): ?array
    {
        return $this->find($siteId, $contentId);
    }

    public function insert(array $record): void
    {
        $this->records[$record['site_id']][$record['content_id']] = $record;
    }

    public function save(array $record, int $expectedVersion): void
    {
        $current = $this->find($record['site_id'], $record['content_id']);
        if ($current === null || $current['version'] !== $expectedVersion) {
            throw new VersionConflict('SEO metadata version is stale.');
        }
        $this->records[$record['site_id']][$record['content_id']] = $record;
    }
};

$store = new InMemoryCmsStore();
$store->permissions['site-a']['editor-1'] = ['content.read', 'seo.manage'];
$store->permissions['site-a']['viewer-1'] = ['content.read'];
$store->contents['site-a']['page-1'] = [
    'site_id' => 'site-a',
    'content_id' => 'page-1',
    'created_by_id' => 'editor-1',
    'type_id' => 'page',
    'slug' => 'accueil',
    'status' => 'published',
    'values' => ['title' => 'Accueil', 'slug' => 'accueil', 'blocks' => []],
    'published_values' => ['title' => 'Accueil', 'slug' => 'accueil', 'blocks' => []],
    'published_slug' => 'accueil',
    'published_version' => 1,
    'published_revision_id' => 'rev-1',
    'version' => 1,
    'current_revision_id' => 'rev-1',
    'publish_at' => null,
    'review_approved_at' => null,
    'published_at' => '2026-10-07 10:00:00',
    'created_at' => '2026-10-07 10:00:00',
    'updated_at' => '2026-10-07 10:00:00',
];

$service = new SeoService($repository, $store, new AuthorizationService($store));
$editor = new ActorContext('site-a', 'editor-1', 'device-1');

$created = $service->save($editor, 'page-1', [
    'title' => 'Accueil — exemple',
    'description' => 'Description utile pour les moteurs.',
    'canonical_url' => '/accueil',
    'social_title' => 'Titre social',
    'social_description' => 'Description sociale',
    'social_image_url' => '/media/share.jpg',
    'indexable' => true,
], 0);
seoCheck($created['seo']['version'] === 1, 'SEO metadata starts at version 1.');
seoCheck($created['site_revision'] === 2, 'SEO mutation advances the site data revision.');
seoCheck(($store->audit[0]['action'] ?? null) === 'seo.create', 'SEO creation is audited.');

$updated = $service->save($editor, 'page-1', [
    'title' => 'Accueil — version 2',
    'description' => null,
    'canonical_url' => 'https://example.test/',
    'social_title' => null,
    'social_description' => null,
    'social_image_url' => null,
    'indexable' => false,
], 1);
seoCheck($updated['seo']['version'] === 2, 'SEO update increments version.');
seoExpect(VersionConflict::class, fn () => $service->save($editor, 'page-1', [], 1));
seoExpect(InvalidArgumentException::class, fn () => SeoMetadataDefinition::normalize([
    'canonical_url' => 'javascript:alert(1)',
]));
seoExpect(InvalidArgumentException::class, fn () => SeoMetadataDefinition::normalize([
    'unknown' => 'x',
]));

$viewer = new ActorContext('site-a', 'viewer-1');
seoCheck($service->get($viewer, 'page-1')['version'] === 2, 'Content reader can read SEO metadata.');
seoExpect(AccessDenied::class, fn () => $service->save($viewer, 'page-1', [], 2));

$head = (new SeoHeadRenderer())->render(
    ['name' => 'Exemple', 'canonical_base_url' => 'https://example.test'],
    ['title' => 'Accueil'],
    [
        'title' => 'Titre <test>',
        'description' => 'Description "sûre"',
        'canonical_url' => '/accueil',
        'social_title' => null,
        'social_description' => null,
        'social_image_url' => '/media/share.jpg',
        'indexable' => false,
    ],
);
seoCheck(str_contains($head, '<title>Titre &lt;test&gt;</title>'), 'SEO title is escaped.');
seoCheck(str_contains($head, 'rel="canonical" href="https://example.test/accueil"'), 'Relative canonical resolves against HTTPS site base.');
seoCheck(str_contains($head, 'name="robots" content="noindex,follow"'), 'Non-indexable pages emit robots metadata.');
seoCheck(str_contains($head, 'property="og:image" content="https://example.test/media/share.jpg"'), 'Social image is rendered as an absolute URL.');

$sitemap = (new SitemapBuilder())->render([
    ['url' => 'https://example.test/', 'last_modified' => '2026-10-07'],
    ['url' => 'https://example.test/private', 'indexable' => false],
    ['url' => 'https://example.test/contact'],
    ['url' => 'https://example.test/contact'],
]);
seoCheck(substr_count($sitemap, '<url>') === 2, 'Sitemap excludes noindex pages and de-duplicates URLs.');
seoCheck(str_contains($sitemap, '<lastmod>2026-10-07</lastmod>'), 'Sitemap emits valid lastmod dates.');
seoExpect(InvalidArgumentException::class, fn () => (new SitemapBuilder())->render([
    ['url' => 'http://example.test/insecure'],
]));

echo "SEO core tests passed.\n";
