<?php
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Content\ContentTypeDefinition;
use Dendrila\Cms\Content\IdempotencyConflict;
use Dendrila\Cms\Content\ScheduledPublisher;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Tests\Support\InMemoryCmsStore;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expect(string $class, callable $operation): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        check($error instanceof $class, "Expected {$class}, got " . $error::class);
        return;
    }
    throw new RuntimeException("Expected {$class} to be thrown.");
}

$store = new InMemoryCmsStore();
$actor = new ActorContext('site-a', 'editor-1', 'device-1');
$store->types['site-a']['page'] = json_decode(
    file_get_contents(dirname(__DIR__) . '/examples/content-types/page.json'), true, 512, JSON_THROW_ON_ERROR,
);
$store->policies['site-a'] = ['review_before_publish' => true];
$store->permissions['site-a']['editor-1'] = [
    'content.create', 'content.read', 'content.read_draft', 'content.update', 'content.preview',
    'content.submit_for_review', 'content.publish', 'content.schedule', 'content.restore_revision',
    'content.archive',
];
$service = new ContentService($store, new AuthorizationService($store));
$values = ['title' => 'Accueil', 'slug' => 'accueil', 'blocks' => [['type' => 'text', 'text' => 'Bonjour']]];
expect(InvalidArgumentException::class, fn () => (new ContentTypeDefinition($store->types['site-a']['page']))->validateValues([
    'title' => 'X', 'slug' => 'x', 'blocks' => [['type' => 'text', 'text' => 'X', 'script' => 'alert(1)']],
]));

$created = $service->createDraft($actor, 'page', $values, 'request-0001');
check($created['content']['version'] === 1, 'New content starts at version 1.');
check($created['site_revision'] === 2, 'Creating content advances the site snapshot revision.');
check(count($store->revisions['site-a']) === 1, 'Creation appends one revision.');
$replayed = $service->createDraft($actor, 'page', $values, 'request-0001');
check($replayed === $created, 'Repeated request returns the original response.');
check(count($store->contents['site-a']) === 1, 'Repeated request does not create content twice.');
expect(IdempotencyConflict::class, fn () => $service->createDraft(
    $actor, 'page', ['title' => 'Autre', 'slug' => 'autre', 'blocks' => []], 'request-0001',
));

$id = $created['content']['id'];
$updated = $service->updateDraft($actor, $id, 1,
    ['title' => 'Accueil modifié', 'slug' => 'accueil', 'blocks' => [['type' => 'text', 'text' => 'Salut']]],
    'request-0002');
check($updated['content']['version'] === 2, 'Update increments the content version.');
check($updated['site_revision'] > $created['site_revision'], 'Draft updates advance the site snapshot revision.');
expect(VersionConflict::class, fn () => $service->updateDraft($actor, $id, 1, $values, 'request-0003'));

$pending = $service->transition($actor, $id, 'published', 2, 'request-0004');
check($pending['status'] === 'pending_review', 'Site review policy prevents direct publication.');
check($pending['content']['status'] === 'review', 'Direct publish request enters review.');
check($pending['site_revision'] > $updated['site_revision'], 'Entering review advances the site snapshot revision.');
$preview = $service->preview($actor, $id);
check($preview['preview'] === true && $preview['status'] === 'review', 'Preview is private and does not publish.');
check($service->get($actor, $id)['content_id'] === $id, 'Site-scoped get returns the item.');
check(count($service->list($actor, ['type' => 'page', 'status' => 'review'])) === 1, 'Bounded list filters by type and status.');

$published = $service->transition($actor, $id, 'published', 3, 'request-0005');
check($published['content']['status'] === 'published', 'Reviewed content can be published.');
check($published['site_revision'] > $pending['site_revision'], 'Publishing advances the site data revision.');
$workingCopy = $service->updateDraft($actor, $id, 4,
    ['title' => 'Nouvelle version', 'slug' => 'nouvel-accueil', 'blocks' => [['type' => 'text', 'text' => 'V2']]],
    'request-0006');
check($workingCopy['content']['status'] === 'draft', 'Editing a published item creates a working draft.');
check($store->contents['site-a'][$id]['published_values']['title'] === 'Accueil modifié', 'Published snapshot remains served while editing.');
$reader = new ActorContext('site-a', 'reader-1');
$store->permissions['site-a']['reader-1'] = ['content.read'];
$publicWhileEditing = $service->get($reader, $id);
check($publicWhileEditing['status'] === 'published' && $publicWhileEditing['slug'] === 'accueil'
    && $publicWhileEditing['values']['title'] === 'Accueil modifié', 'Read-only role receives only the last public version.');

$publishAt = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify('+2 hours');
$publishAtIso = $publishAt->format(DATE_ATOM);
$needsReview = $service->transition($actor, $id, 'scheduled', 5, 'request-0008', $publishAtIso);
check($needsReview['status'] === 'pending_review' && $needsReview['content']['status'] === 'review', 'Review policy gates a new scheduled publication.');
$scheduled = $service->transition($actor, $id, 'scheduled', 6, 'request-0009', $publishAtIso);
check($scheduled['content']['status'] === 'scheduled', 'Authorized editor can schedule reviewed content.');
$publisher = new ScheduledPublisher($store);
$publishedByCron = $publisher->runForSite('site-a', $publishAt->modify('-1 hour'));
check(count($publishedByCron) === 0, 'Scheduler does not publish before its due time.');
$publishedByCron = $publisher->runForSite('site-a', $publishAt);
check(count($publishedByCron) === 1 && $publishedByCron[0]['status'] === 'published', 'Due scheduled content is published once.');
$publishedVersion = $publishedByCron[0]['version'];
$restored = $service->restoreRevision($actor, $id, $created['content']['current_revision_id'],
    $publishedVersion, 'request-0010');
check($restored['content']['status'] === 'draft', 'Restoring an old revision creates a working draft.');
check($restored['content']['version'] === $publishedVersion + 1, 'Restoration does not roll back the version counter.');
check(count($publisher->runForSite('site-a', $publishAt->modify('+1 hour'))) === 0,
    'A scheduled item is not published twice.');

$contributor = new ActorContext('site-a', 'contributor-1');
$store->permissions['site-a']['contributor-1'] = [
    'content.read', 'content.read_own_draft', 'content.create', 'content.update_own',
    'content.submit_for_review', 'content.preview',
];
$ownDraft = $service->createDraft($contributor, 'page', [
    'title' => 'Mon brouillon', 'slug' => 'mon-brouillon', 'blocks' => [],
], 'request-0011');
check($service->get($contributor, $ownDraft['content']['id'])['values']['title'] === 'Mon brouillon',
    'Contributor can read their own draft.');
check(count($service->list($contributor, ['status' => 'draft'])) === 1,
    'Contributor draft listing is limited to their own drafts.');
$publicForContributor = $service->get($contributor, $id);
check($publicForContributor['status'] === 'published'
    && $publicForContributor['values']['title'] === 'Nouvelle version'
    && $publicForContributor['version'] === $publishedVersion,
    'Contributor sees the public snapshot, not another editor working draft.');
expect(Dendrila\Cms\Content\ContentNotFound::class, fn () => $service->updateDraft(
    $contributor, $id, $store->contents['site-a'][$id]['version'], $values, 'request-0012'));
$unpublishedDraft = $service->createDraft($actor, 'page', [
    'title' => 'Brouillon privé', 'slug' => 'brouillon-prive', 'blocks' => [],
], 'request-0014');
expect(Dendrila\Cms\Content\ContentNotFound::class, fn () =>
    $service->get($contributor, $unpublishedDraft['content']['id']));
$privateVersion = $store->contents['site-a'][$unpublishedDraft['content']['id']]['version'];
expect(Dendrila\Cms\Content\ContentNotFound::class, fn () => $service->updateDraft(
    $contributor, $unpublishedDraft['content']['id'], $privateVersion,
    ['title' => 'Pirate', 'slug' => 'brouillon-prive', 'blocks' => []], 'request-0015'));
$ownUpdated = $service->updateDraft($contributor, $ownDraft['content']['id'], 1, [
    'title' => 'Mon brouillon modifié', 'slug' => 'mon-brouillon', 'blocks' => [],
], 'request-0013');
check($ownUpdated['content']['version'] === 2, 'Contributor can update their own draft.');
$partialUpdate = $service->updateFields($contributor, $ownDraft['content']['id'], ['title' => 'Titre partiel'], 'request-0016', 'page');
check($partialUpdate['content']['values']['title'] === 'Titre partiel'
    && $partialUpdate['content']['values']['blocks'] === [],
    'Mapped-field updates preserve CMS fields not exposed to the mobile client.');
check($service->updateFields($contributor, $ownDraft['content']['id'], ['title' => 'Titre partiel'],
    'request-0016', 'page') === $partialUpdate, 'Mapped-field updates replay their stored response idempotently.');
$reviewUpdate = $service->updateFields($contributor, $ownDraft['content']['id'], ['title' => 'À valider'],
    'request-0018', 'page', true);
check($reviewUpdate['status'] === 'pending_review' && $reviewUpdate['content']['status'] === 'review',
    'MMW field updates enter review when the site policy requires it.');
$reviewCreate = $service->createDraft($actor, 'page', [
    'title' => 'Nouvelle page depuis MMW', 'slug' => 'nouvelle-page-mmw', 'blocks' => [],
], 'request-0019', true);
check($reviewCreate['status'] === 'pending_review' && $reviewCreate['content']['status'] === 'review',
    'MMW-created content enters review when the site policy requires it.');
$archived = $service->archive($actor, $unpublishedDraft['content']['id'], 'request-0017', 'page');
check($archived['content']['status'] === 'archived', 'A gallery deletion archives the CMS content.');
check($service->archive($actor, $unpublishedDraft['content']['id'], 'request-0017', 'page') === $archived,
    'Repeated gallery deletion returns the original idempotent response.');

$foreignActor = new ActorContext('site-b', 'editor-1');
$store->permissions['site-b']['editor-1'] = ['content.read'];
expect(AccessDenied::class, fn () => $service->updateDraft($foreignActor, $id, 5, $values, 'request-0007'));
check(!isset($store->contents['site-b'][$id]), 'Cross-site identifier does not expose or mutate content.');
expect(Dendrila\Cms\Content\ContentNotFound::class, fn () => $service->get($foreignActor, $id));

echo "CMS core contract tests passed (" . count($store->audit) . " audit events).\n";
