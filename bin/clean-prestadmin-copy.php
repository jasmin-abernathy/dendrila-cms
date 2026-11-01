<?php
declare(strict_types=1);
/**
 * One-time, replay-safe Prestadmin editorial copy cleanup.
 * Restricted to the owner-installed staging SQLite site: no live edits, no
 * password or TOTP changes, and no raw SQL updates to published revisions.
 */
require dirname(__DIR__) . '/bootstrap.php';

use Dendrila\Cms\Runtime\Application;
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Authorization\{ActorContext, AuthorizationService};
use Dendrila\Cms\Content\{ContentService, ContentTypeDefinition};

$apply = in_array('--apply', $argv, true);
if (!$apply && !in_array('--dry-run', $argv, true)) {
    fwrite(STDERR, "Usage: php bin/clean-prestadmin-copy.php --dry-run|--apply\n");
    exit(2);
}
if (getenv('APP_BASE_URL') !== 'https://prestadmin-test.lepotager.org'
    || !str_starts_with(getenv('DB_DSN') ?: '', 'sqlite:')
    || getenv('DENDRILA_SITE_ID') !== 'default') {
    throw new RuntimeException('Prestadmin staging SQLite only.');
}
$pdo = Application::database();
$actor = new ActorContext('default', 'owner');
$store = new PdoCmsStore($pdo);
$service = new ContentService($store, new AuthorizationService($store));
$pages = $service->list($actor, ['type' => 'page', 'limit' => 100]);
$home = null;
foreach ($pages as $page) {
    if (($page['slug'] ?? '') === 'home') { $home = $page; break; }
}
if ($home === null || $home['status'] !== 'published') {
    throw new RuntimeException('Published homepage not found.');
}
$old = 'Expliquez la situation via la page de contact : un échange humain permettra de définir la bonne intervention.';
$newText = 'Décrivez votre situation depuis la page Contact : Prestadmin précisera les tâches à réaliser et les modalités d’intervention.';
$values = $home['values'];
$found = 0;
foreach ($values['blocks'] as &$block) {
    if (($block['type'] ?? '') !== 'guided_assistant' || !is_array($block['items'] ?? null)) continue;
    foreach ($block['items'] as &$item) {
        if (($item['text'] ?? null) !== $old) continue;
        $item['text'] = $newText;
        ++$found;
    }
    unset($item);
}
unset($block);
if ($found === 0) { echo "PRESTADMIN_COPY_ALREADY_CLEAN\n"; exit(0); }
if ($found !== 1) throw new RuntimeException('Unexpected homepage content; refusing multi-edit.');
$schema = $store->findContentType('default', 'page');
(new ContentTypeDefinition($schema))->validateValues($values);
if (!$apply) { echo "PRESTADMIN_COPY_DRY_RUN_OK matches=1\n"; exit(0); }
$directory = dirname(__DIR__, 2) . '/backups';
if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
    throw new RuntimeException('Unable to create private backup directory.');
}
$backup = $directory . '/before-prestadmin-copy-' . gmdate('Ymd-His') . '.sqlite';
$pdo->exec('VACUUM INTO ' . $pdo->quote($backup));
chmod($backup, 0600);
$draft = $service->updateDraft($actor, $home['content_id'], (int) $home['version'], $values, 'prestadmin-copy-concrete');
$record = $draft['content'];
$service->transition($actor, $record['id'], 'published', $record['version'], 'prestadmin-copy-published');
echo "PRESTADMIN_COPY_PUBLISHED=YES\nBACKUP_CREATED=YES\n";
