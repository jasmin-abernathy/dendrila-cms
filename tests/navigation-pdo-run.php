<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Front\PdoPublicNavigationSource;
use Dendrila\Cms\Navigation\NavigationService;
use Dendrila\Cms\Navigation\PdoNavigationRepository;
use Dendrila\Cms\Storage\PdoCmsStore;

function navigationPdoCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function navigationPdoExpect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        navigationPdoCheck($error instanceof $class, "Expected {$class}, got " . $error::class);
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
$navigation = new PdoNavigationRepository($pdo);
$service = new NavigationService($navigation, $store, new AuthorizationService($store));
$actor = new ActorContext('site-native', 'owner-native', 'pdo-test');

$created = $service->save($actor, 'main', 'Navigation principale', 'primary', [
    ['id' => 'home', 'label' => 'Accueil', 'url' => '/'],
    ['id' => 'booking', 'label' => 'Réserver', 'url' => '/reserver'],
    ['id' => 'hidden', 'label' => 'Brouillon', 'url' => '/brouillon', 'visible' => false],
], 0);
navigationPdoCheck($created['menu']['version'] === 1, 'PDO navigation creates version 1.');

$public = new PdoPublicNavigationSource($pdo);
navigationPdoCheck($public->items('site-native', 'primary') === [
    ['label' => 'Accueil', 'url' => '/'],
    ['label' => 'Réserver', 'url' => '/reserver'],
], 'Public navigation exposes only ordered visible links.');

$updated = $service->save($actor, 'main', 'Navigation principale', 'primary', [
    ['id' => 'booking', 'label' => 'Réserver', 'url' => '/reserver'],
    ['id' => 'home', 'label' => 'Accueil', 'url' => '/'],
], 1);
navigationPdoCheck($updated['menu']['version'] === 2, 'PDO navigation update increments version.');
navigationPdoCheck(array_column($service->get($actor, 'main')['items'], 'id') === ['booking', 'home'],
    'PDO navigation preserves explicit ordering.');
navigationPdoExpect(VersionConflict::class, fn () => $service->save(
    $actor, 'main', 'Stale', 'primary', [], 1,
));

$deleted = $service->delete($actor, 'main', 2);
navigationPdoCheck($deleted['menu_id'] === 'main', 'PDO navigation menu can be deleted.');
navigationPdoCheck($public->items('site-native', 'primary') === [], 'Deleted navigation is no longer public.');

echo "Navigation PDO tests passed.\n";
