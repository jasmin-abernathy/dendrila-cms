<?php
declare(strict_types=1);

// Dedicated, already-migrated test database only. Never point this at production.
require dirname(__DIR__) . '/vendor/autoload.php';

use LePotager\Cms\Authorization\ActorContext;
use LePotager\Cms\Authorization\AuthorizationService;
use LePotager\Cms\Authorization\AccessDenied;
use LePotager\Cms\Design\DesignStyleDefinition;
use LePotager\Cms\Design\SiteComposition;
use LePotager\Cms\Design\PdoCompositionService;
use LePotager\Cms\Storage\PdoCmsStore;

if (getenv('CMS_COMPOSITION_TEST_DB') !== '1' || !getenv('TEST_DB_DSN')) {
    fwrite(STDERR, "Set CMS_COMPOSITION_TEST_DB=1 and TEST_DB_DSN for a dedicated migrated database.\n");
    exit(2);
}
$pdo = new PDO(getenv('TEST_DB_DSN'), getenv('TEST_DB_USERNAME') ?: '', getenv('TEST_DB_PASSWORD') ?: '',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
$service = new PdoCompositionService($pdo, new AuthorizationService(new PdoCmsStore($pdo)));
$siteId = 'composition-test-' . bin2hex(random_bytes(6));
$actor = new ActorContext($siteId, 'test-owner');
$styleData = json_decode(file_get_contents(dirname(__DIR__) . '/design-styles/quiet-elegant/style.json'), true, 64, JSON_THROW_ON_ERROR);
$style = new DesignStyleDefinition($styleData);
$catalog = [$style->id()=>$style];
$base = ['schema_version'=>1,'functional_kit'=>'generic','design_style'=>$style->id()];
$first = new SiteComposition($base, $catalog, ['generic'], []);
$second = new SiteComposition($base + ['style_overrides'=>['layout'=>['alignment'=>'center']]], $catalog, ['generic'], []);
$check = static function (bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); };
try {
    $q = $pdo->prepare("INSERT INTO sites (site_id,display_name,policy_json,created_at,updated_at) VALUES (:site,'Composition test','{}',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))");
    $q->execute(['site'=>$siteId]);
    $q = $pdo->prepare("INSERT INTO roles (site_id,role_id,label) VALUES (:site,'composition-tester','Composition tester')");
    $q->execute(['site'=>$siteId]);
    $q = $pdo->prepare("INSERT INTO memberships (site_id,actor_id,role_id,created_at) VALUES (:site,'test-owner','composition-tester',UTC_TIMESTAMP(6))");
    $q->execute(['site'=>$siteId]);
    foreach (['composition.manage','composition.publish'] as $permission) {
        $q = $pdo->prepare("INSERT INTO role_permissions (site_id,role_id,permission) VALUES (:site,'composition-tester',:permission)");
        $q->execute(['site'=>$siteId,'permission'=>$permission]);
    }
    $denied = false;
    try { $service->saveDraft(new ActorContext($siteId, 'stranger'), $first, 0); }
    catch (AccessDenied) { $denied = true; }
    $check($denied, 'Unknown actor cannot save composition.');
    $check($service->saveDraft($actor, $first, 0) === 1, 'First draft version is 1.');
    $check($service->published($siteId) === null, 'Draft is never public.');
    $conflict = false;
    try { $service->saveDraft($actor, $second, 0); } catch (RuntimeException) { $conflict = true; }
    $check($conflict && $service->draft($actor)['version'] === 1, 'Stale draft is rejected without changing state.');
    $conflict = false;
    try { $service->publish($actor, $second, 1); } catch (RuntimeException) { $conflict = true; }
    $check($conflict && $service->revision($actor, 2) === null, 'Unreviewed composition cannot publish; revision is rolled back.');
    $check($service->publish($actor, $first, 1) === 2, 'Reviewed draft publishes.');
    $check($service->saveDraft($actor, $second, 2) === 3, 'Next change is a new draft.');
    $check($service->published($siteId) === $first->toArray(), 'Editing preserves published composition.');
    $check($service->revision($actor, 1) === $first->toArray(), 'Old snapshot remains immutable.');
    $restored = new SiteComposition($service->revision($actor, 1), $catalog, ['generic'], []);
    $check($service->restoreDraft($actor, $restored, 3) === 4, 'Restore adds a new draft revision.');
    $check($service->draft($actor)['composition'] === $first->toArray(), 'Restored draft matches historical snapshot.');
    $denied = false;
    try { $service->revision(new ActorContext('other-site', 'test-owner'), 1); } catch (AccessDenied) { $denied = true; }
    $check($denied, 'Same actor has no rights in a different site.');
    echo "Composition MariaDB tests passed.\n";
} finally {
    if ($pdo->inTransaction()) $pdo->rollBack();
    // Only the unique fixture's rows are removed.
    foreach (['audit_events','memberships','role_permissions','roles','sites'] as $table) {
        $q = $pdo->prepare('DELETE FROM ' . $table . ' WHERE site_id = :site');
        $q->execute(['site'=>$siteId]);
    }
}
