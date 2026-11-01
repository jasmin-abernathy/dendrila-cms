<?php
declare(strict_types=1);
/**
 * Minimal staging-only migration. Does not update live production, accounts,
 * legal drafts, navigation or payment data. Safe to rerun.
 */
require dirname(__DIR__) . '/bootstrap.php';

use Dendrila\Cms\Runtime\Application;
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Authorization\{ActorContext, AuthorizationService};
use Dendrila\Cms\Content\{ContentService, ContentTypeDefinition};

$apply = in_array('--apply', $argv, true);
if (!$apply && !in_array('--dry-run', $argv, true)) {
    fwrite(STDERR, "Usage: php bin/repair-prestadmin-content-affordances.php --dry-run|--apply\n");
    exit(2);
}
if (getenv('APP_BASE_URL') !== 'https://prestadmin-test.lepotager.org'
    || !str_starts_with(getenv('DB_DSN') ?: '', 'sqlite:')
    || getenv('DENDRILA_SITE_ID') !== 'default') {
    throw new RuntimeException('Restricted to Prestadmin SQLite staging.');
}
$pdo = Application::database();
$actor = new ActorContext('default', 'owner');
$store = new PdoCmsStore($pdo);
$service = new ContentService($store, new AuthorizationService($store));
$schema = new ContentTypeDefinition($store->findContentType('default', 'page'));
$pages = $service->list($actor, ['type' => 'page', 'limit' => 100]);
$pending = [];
$labelsCount = 0;
$escapesCount = 0;
foreach ($pages as $page) {
    if (($page['status'] ?? '') !== 'published') continue;
    $values = $page['values'];
    $changed = false;
    foreach ($values['blocks'] as &$block) {
        if (($page['slug'] ?? '') === 'home' && ($block['type'] ?? '') === 'image_text'
            && is_string($block['text'] ?? null) && str_contains($block['text'], '\\n')) {
            // Repair the escaped paragraph breaks in one imported presentation,
            // not genuine newlines or unrelated fields elsewhere.
            $escapesCount += substr_count($block['text'], '\\n');
            $block['text'] = str_replace('\\n', "\n", $block['text']);
            $changed = true;
        }
        if (($block['type'] ?? '') !== 'cards' || !is_array($block['items'] ?? null)) continue;
        foreach ($block['items'] as &$item) {
            if (!is_string($item['url'] ?? null) || $item['url'] === ''
                || (isset($item['label']) && trim((string) $item['label']) !== '')) continue;
            $url = $item['url'];
            if (str_contains($url, '/projet/')) {
                $label = 'Préparer ma demande';
            } elseif (str_contains($url, 'rgpd.prestadmin57.fr')) {
                $label = 'Faire le diagnostic';
            } elseif (str_contains($url, 'facturation-electronique')) {
                $label = 'Voir la prestation';
            } elseif (str_contains($url, '/outils/siret/')) {
                $label = 'Ouvrir la recherche';
            } else {
                $label = 'En savoir plus';
            }
            $item['label'] = $label;
            ++$labelsCount;
            $changed = true;
        }
        unset($item);
    }
    unset($block);
    if (!$changed) continue;
    $schema->validateValues($values);
    $pending[] = ['page' => $page, 'values' => $values];
}
printf("%s pages=%d linked_cards=%d escaped_newlines=%d\n",
    $apply ? 'APPLY' : 'DRY_RUN', count($pending), $labelsCount, $escapesCount);
if (!$apply || $pending === []) exit(0);
$directory = dirname(__DIR__, 2) . '/backups';
if (!is_dir($directory) && !mkdir($directory, 0700, true)) {
    throw new RuntimeException('Unable to create a private backup directory.');
}
$backup = $directory . '/before-prestadmin-affordances-' . gmdate('Ymd-His') . '.sqlite';
$pdo->exec('VACUUM INTO ' . $pdo->quote($backup));
chmod($backup, 0600);
foreach ($pending as $change) {
    $page = $change['page'];
    $draft = $service->updateDraft($actor, $page['content_id'], (int) $page['version'],
        $change['values'], 'prestadmin-card-action-and-paragraph-repair');
    $record = $draft['content'];
    $service->transition($actor, $record['id'], 'published', $record['version'],
        'prestadmin-card-action-and-paragraph-published');
    echo "UPDATED=" . $page['slug'] . "\n";
}
echo "PRIVATE_BACKUP_CREATED=YES\n";
