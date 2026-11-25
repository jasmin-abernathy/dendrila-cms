<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Oldweb\PortalAppearance;
use Dendrila\Cms\Oldweb\PortalStorage;
use Dendrila\Cms\Oldweb\PortalThemes;

function portalCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function portalReject(callable $operation, string $class = InvalidArgumentException::class): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        portalCheck($error instanceof $class, 'Unexpected error ' . $error::class);
        return;
    }
    throw new RuntimeException('Invalid portal operation was accepted.');
}

$directory = sys_get_temp_dir() . '/oldweb-test-' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
try {
    $root = dirname(__DIR__) . '/public';
    portalReject(fn () => new PortalStorage($root, $root), RuntimeException::class);
    $store = new PortalStorage($directory, $root);
    $defaults = PortalAppearance::defaults();
    $zones = $defaults;
    $zones['banner']['background'] = '#152038';
    $zones['banner']['border'] = 'double';

    $gif = base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==', true);
    $asset = PortalStorage::assetName($gif);
    $upload = ['name' => $asset, 'bytes' => $gif];
    $invalid = $zones;
    $invalid['intro']['background'] = 'url(photo)';
    portalReject(fn () => $store->change(0, 'draft', $invalid, []));
    $invalid = $zones;
    $invalid['intro']['text'] = $zones['intro']['background'];
    portalReject(fn () => $store->change(0, 'draft', $invalid, []));
    portalReject(fn () => $store->change(0, 'draft', $zones, ['menu' => $upload]));
    portalReject(fn () => PortalStorage::assetName('non-image'));
    portalReject(fn () => PortalStorage::assetName(str_repeat('a', PortalStorage::MAX_UPLOAD + 1)));
    portalCheck($store->state()['revision'] === 0, 'Rejected saves should leave the revision intact.');

    portalCheck($store->change(0, 'draft', $zones, ['banner' => $upload]), 'Draft save must succeed.');
    $state = $store->state();
    portalCheck($state['published'] === $defaults, 'Draft should leave the public page intact.');
    portalCheck($store->image($asset, $state['published']) === null, 'Draft assets must stay private.');
    portalCheck($store->image($asset, $state['draft'])['mime'] === 'image/gif', 'Editor should see the draft GIF.');
    portalCheck($store->image('../appearance.json', $state['draft']) === null, 'Arbitrary file reads must be denied.');
    portalCheck(!$store->change(0, 'publish', $zones, []), 'A stale revision cannot publish.');
    portalCheck($store->change(1, 'publish', $zones, []), 'Current revision should publish.');
    portalCheck($store->image($asset, $store->state()['published']) !== null, 'Published media should be visible.');
    portalCheck($store->change(2, 'restore', [], []), 'Previous appearance should be restored.');
    portalCheck($store->state()['published'] === $defaults, 'Restoration should recover the original appearance.');
    $legacy = $store->state();
    unset($legacy['themes'], $legacy['site']);
    file_put_contents($directory . '/appearance.json', json_encode($legacy, JSON_THROW_ON_ERROR));
    portalCheck($store->state()['themes']['published'] === PortalThemes::DEFAULT, 'Older installations fall back to Canal 57.');
    portalCheck($store->state()['revision'] === 3, 'Reading a legacy installation must preserve its revision.');
    portalReject(fn () => $store->change(3, 'draft', $zones, [], 'unknown'));
    portalCheck($store->state()['revision'] === 3, 'An unknown theme must leave the revision intact.');
    portalCheck(PortalThemes::forRendering('removed') === PortalThemes::AVAILABLE[PortalThemes::DEFAULT], 'Missing themes must fall back.');

    unlink($directory . '/appearance.json');
    portalReject(fn () => $store->change(0, 'install', ['name' => ' ', 'tagline' => ''], []));
    portalCheck($store->change(0, 'install', ['name' => 'Radio de quartier', 'tagline' => 'Émissions locales'], []), 'New site should install.');
    $installed = $store->state();
    portalCheck($installed['revision'] === 1 && $installed['site']['name'] === 'Radio de quartier', 'The wizard must persist the site name.');
    portalCheck($installed['published'] === PortalThemes::initialAppearance(PortalThemes::DEFAULT), 'Install should use the theme defaults.');
    portalCheck(!$store->change(0, 'install', ['name' => 'Autre site', 'tagline' => ''], []), 'Two setup requests must not overwrite one another.');
    portalReject(fn () => $store->change(1, 'install', ['name' => 'Autre site', 'tagline' => ''], []));
    portalCheck($store->state() === $installed, 'An installed site must never be reinitialized.');
} finally {
    foreach (glob($directory . '/*') as $path) {
        unlink($path);
    }
    rmdir($directory);
}
echo "Oldweb fixed portal tests passed.\n";
