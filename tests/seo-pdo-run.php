<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Front\PdoPublicSeoSource;
use Dendrila\Cms\Seo\PdoSeoRepository;
use Dendrila\Cms\Seo\SeoService;
use Dendrila\Cms\Storage\PdoCmsStore;

function seoPdoCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function seoPdoExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        seoPdoCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$dsn = getenv('DB_DSN') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';
if ($dsn === '' || $username === '') {
    fwrite(STDERR, "DB_DSN and DB_USERNAME are required.\n");
    exit(2);
}

$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

$store = new PdoCmsStore($pdo);
$authorization = new AuthorizationService($store);
$actor = new ActorContext('site-native', 'owner-native', 'seo-pdo-test');
$content = (new ContentService($store, $authorization))->createDraft(
    $actor,
    'page',
    ['title' => 'Page SEO', 'slug' => 'page-seo', 'blocks' => []],
    'seo-pdo-create-page',
);
$contentId = $content['content']['id'];

$service = new SeoService(new PdoSeoRepository($pdo), $store, $authorization);
$created = $service->save($actor, $contentId, [
    'title' => 'Page SEO optimisée',
    'description' => 'Description de test',
    'canonical_url' => '/page-seo',
    'social_title' => 'Partage SEO',
    'social_description' => null,
    'social_image_url' => '/media/seo.jpg',
    'indexable' => true,
], 0);
seoPdoCheck($created['seo']['version'] === 1, 'PDO SEO starts at version 1.');

$public = new PdoPublicSeoSource($pdo);
$metadata = $public->metadata('site-native', $contentId);
seoPdoCheck($metadata['canonical_url'] === '/page-seo', 'Public SEO source returns canonical metadata.');
seoPdoCheck($metadata['indexable'] === true, 'Public SEO source preserves indexable flag.');

$updated = $service->save($actor, $contentId, [
    'title' => null,
    'description' => null,
    'canonical_url' => 'https://example.test/page-seo',
    'social_title' => null,
    'social_description' => null,
    'social_image_url' => null,
    'indexable' => false,
], 1);
seoPdoCheck($updated['seo']['version'] === 2, 'PDO SEO update increments version.');
seoPdoExpect(VersionConflict::class, fn () => $service->save($actor, $contentId, [], 1));

echo "SEO PDO tests passed.\n";
