<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Content\ContentNotFound;
use Dendrila\Cms\Content\IdempotencyConflict;
use Dendrila\Cms\Http\HttpException;
use Dendrila\Cms\Http\UploadedFile;
use Dendrila\Cms\Http\Request;
use Dendrila\Cms\Api\{MediaLibraryModule, PdoSiteReadModel, PdoSiteChangeService, ApiKernel, DeviceAuthenticator};
use Dendrila\Cms\Media\{MediaService, ImageStorage, MediaReferences};
use Dendrila\Cms\Storage\PdoCmsStore;

function mc(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function me(string $class, callable $fn): void {
    try { $fn(); } catch (Throwable $e) {
        if (!$e instanceof $class) throw new RuntimeException('Expected ' . $class . ', got ' . $e::class . ': ' . $e->getMessage(), previous: $e);
        return;
    }
    throw new RuntimeException('Expected ' . $class);
}
$pdo = new PDO(getenv('DB_DSN'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$root = sys_get_temp_dir() . '/media-pdo-' . bin2hex(random_bytes(8)); mkdir($root, 0700);
$storage = new ImageStorage($root);
$service = new MediaService($pdo, $storage, 'https://example.test/media.php');
$actor = new ActorContext('site-native', 'owner-native', 'media-test');
$module = MediaLibraryModule::definition();
$q = $pdo->prepare('UPDATE site_api_settings SET modules_json = ? WHERE site_id = ?');
$q->execute([json_encode([$module]), $actor->siteId]);
$pdo->exec("INSERT INTO sites (site_id, display_name, policy_json, created_at, updated_at) VALUES ('media-other', 'Other', '{}', NOW(), NOW())");
$pdo->exec("INSERT INTO roles (site_id, role_id, label) VALUES ('media-other', 'owner', 'Owner'), ('site-native', 'media-reader', 'Reader'), ('site-native', 'plain-reader', 'Content only')");
$pdo->exec("INSERT INTO memberships (site_id, actor_id, role_id, created_at) VALUES ('media-other', 'other-owner', 'owner', NOW()), ('site-native', 'media-reader', 'media-reader', NOW()), ('site-native', 'plain-reader', 'plain-reader', NOW())");
foreach (['media.read', 'media.upload', 'media.update', 'media.delete'] as $permission) {
    $pdo->prepare("INSERT INTO role_permissions (site_id, role_id, permission) VALUES ('media-other', 'owner', ?)")->execute([$permission]);
}
$pdo->exec("INSERT INTO role_permissions (site_id, role_id, permission) VALUES ('site-native','media-reader','media.read'), ('site-native','plain-reader','content.read')");
$pdo->prepare("INSERT INTO site_api_settings (site_id, branding_json, modules_json, created_at, updated_at) VALUES ('media-other', '{}', ?, NOW(), NOW())")->execute([json_encode([$module])]);
$other = new ActorContext('media-other', 'other-owner', 'device');
$reader = new ActorContext('site-native', 'media-reader', 'device');
$plain = new ActorContext('site-native', 'plain-reader', 'device');

try {
    $path = $root . '/input.png'; $im = imagecreatetruecolor(40, 20); imagepng($im, $path);
    $file = new UploadedFile($path, 'photo.png');
    $fields = ['module_id' => 'media_library', 'item_id' => 'media_library', 'client_request_id' => 'media-upload-0001', 'metadata' => '{"alt":"Un arbre","title":"Jardin","credit":"Jasmin"}'];
    $a = $service->upload($actor, $fields, $file);
    mc($a === $service->upload($actor, $fields, $file), 'Upload retry identical.');
    $id = $a['result']['id'];
    mc($a['result']['revision'] === 1 && $a['result']['version'] === 1, 'Initial revision and pixel version.');
    mc((int)$pdo->query("SELECT COUNT(*) FROM media_assets WHERE site_id = 'site-native'")->fetchColumn() === 1, 'One asset only.');
    $conflict = $fields; $conflict['metadata'] = '{"title":"Different"}';
    me(IdempotencyConflict::class, fn () => $service->upload($actor, $conflict, $file));
    me(AccessDenied::class, fn () => $service->upload($reader, $fields, $file));
    $read = new PdoSiteReadModel($pdo, $service);
    $snapshot = $read->snapshot($actor);
    $item = $snapshot['data']->media_library['items'][0];
    mc($item['id'] === $id && $item['parent_id'] === 'media_library' && str_starts_with($item['thumb'], 'https://example.test/media.php?'), 'Snapshot contract.');
    mc($item['original_width'] === 40 && $item['original_height'] === 20 && $item['bytes'] === filesize($path), 'Original measurements exposed explicitly.');
    mc($item['variants']['web']['width'] === 40 && $item['variants']['web']['mime'] === 'image/webp', 'Derivative has its own measurements and MIME.');
    mc(str_ends_with($item['created_at'], 'Z') && $item['revision'] === 1, 'UTC date and revision exposed.');
    mc(!str_contains(json_encode($snapshot), $root) && !str_contains(json_encode($snapshot), 'original_key'), 'No private paths.');
    mc(!isset($read->snapshot($plain)['data']->media_library), 'Content reader cannot see media.');
    mc(count($service->items($other, $module)) === 0, 'Cross-site list empty.');
    mc(count($service->items($actor, $module, ['query' => 'arbre'])) === 1, 'Search alt.');
    mc(count($service->items($actor, $module, ['query' => 'jasmin'])) === 1, 'Search credit.');
    mc(count($service->items($actor, $module, ['mime' => 'image/jpeg'])) === 0, 'MIME filter.');
    me(AccessDenied::class, fn () => $service->items($reader, $module, ['usage' => 'used']));
    $payload = ['item_id' => $id, 'parent_id' => 'media_library'];
    $q = $pdo->prepare('SELECT original_key FROM media_assets WHERE site_id = ? AND media_id = ?'); $q->execute([$actor->siteId,$id]); $original = $q->fetchColumn();
    $hash = hash_file('sha256', $storage->path($original));
    $rotated = $service->change($actor, 'media_library', 'rotate_right', $payload, 'media-rotation-001');
    mc($rotated === $service->change($actor, 'media_library', 'rotate_right', $payload, 'media-rotation-001'), 'Rotation retry identical.');
    mc($rotated['result']['rotation'] === 90 && hash_file('sha256', $storage->path($original)) === $hash, 'Original preserved on rotation.');
    $rotatedItem = $service->items($actor, $module)[0];
    mc($rotatedItem['revision'] === 2 && $rotatedItem['version'] === 2, 'Rotation increments revision and pixel version.');
    mc($rotatedItem['original_width'] === 40 && $rotatedItem['variants']['web']['width'] === 20, 'Original and displayed dimensions remain distinct.');
    me(ContentNotFound::class, fn () => $service->change($other, 'media_library', 'rotate_right', $payload, 'other-rotation-001'));
    me(AccessDenied::class, fn () => $service->change($reader, 'media_library', 'rotate_right', $payload, 'reader-rotation-001'));
    me(InvalidArgumentException::class, fn () => $service->change($actor, 'media_library', 'update_item', $payload + ['alt' => []], 'bad-meta-array-001'));
    me(InvalidArgumentException::class, fn () => $service->change($actor, 'media_library', 'update_item', $payload + ['alt' => str_repeat('a', 2001)], 'bad-meta-length-001'));
    me(InvalidArgumentException::class, fn () => $service->change($actor, 'media_library', 'update_item', $payload + ['allow_delete' => true], 'bad-meta-option-001'));
    $updated = $service->change($actor, 'media_library', 'update_item', $payload + ['alt' => 'Un pommier'], 'update-media-alt-001');
    mc($service->items($actor, $module)[0]['alt'] === 'Un pommier', 'Metadata updated.');
    mc($updated['result']['revision'] === 3 && $updated['result']['version'] === 2, 'Metadata increments revision without changing pixels.');
    $stale = $payload + ['alt' => 'Overwrite refused', 'expected_revision' => 2];
    me(\Dendrila\Cms\Content\VersionConflict::class, fn () => $service->change($actor, 'media_library', 'update_item', $stale, 'stale-metadata-001'));
    me(\Dendrila\Cms\Content\VersionConflict::class, fn () => $service->change($actor, 'media_library', 'rotate_left', $payload + ['expected_revision' => 2], 'stale-rotation-001'));
    me(\Dendrila\Cms\Content\VersionConflict::class, fn () => $service->change($actor, 'media_library', 'delete_item', $payload + ['expected_revision' => 2], 'stale-delete-001'));
    me(InvalidArgumentException::class, fn () => $service->change($actor, 'media_library', 'update_item', $payload + ['alt' => 'Bad', 'expected_revision' => '3'], 'invalid-revision-001'));
    mc($service->items($actor, $module)[0]['alt'] === 'Un pommier', 'Stale writes change nothing.');
    $fresh = $payload + ['alt' => 'Un pommier', 'expected_revision' => 3];
    $saved = $service->change($actor, 'media_library', 'update_item', $fresh, 'fresh-metadata-001');
    mc($saved['result']['revision'] === 4, 'Current revision accepted.');
    mc($saved === $service->change($actor, 'media_library', 'update_item', $fresh, 'fresh-metadata-001'), 'Applied retry bypasses stale revision check.');
    $migration = file_get_contents(dirname(__DIR__) . '/database/migrations/008_media_revision.sql');
    $pdo->exec($migration); $pdo->exec($migration);
    mc($service->items($actor, $module)[0]['revision'] === 4, 'Reapplying additive migration preserves data and revisions.');
    $disabled = $module; $disabled['options'] = ['allow_rotate' => false, 'allow_delete' => false];
    $pdo->prepare('UPDATE site_api_settings SET modules_json = ? WHERE site_id = ?')->execute([json_encode([$disabled]), $actor->siteId]);
    me(AccessDenied::class, fn () => $service->change($actor, 'media_library', 'rotate_right', $payload + ['allow_rotate' => true], 'forged-rotate-001'));
    me(AccessDenied::class, fn () => $service->change($actor, 'media_library', 'delete_item', $payload, 'disabled-delete-001'));
    $pdo->prepare('UPDATE site_api_settings SET modules_json = ? WHERE site_id = ?')->execute([json_encode([$module]), $actor->siteId]);

    $store = new PdoCmsStore($pdo); $content = new ContentService($store, new AuthorizationService($store));
    $draft = $content->createDraft($actor, 'page', ['title' => 'Media usage', 'slug' => 'media-usage', 'blocks' => [['type' => 'image', 'media_id' => $id, 'alt' => 'Contextual alt']]], 'media-draft-001');
    $cid = $draft['content']['id'];
    me(LogicException::class, fn () => MediaReferences::guardContent($pdo, ['site_id' => $actor->siteId, 'status' => 'draft', 'values' => ['cover' => $id]], ['fields' => [['id' => 'cover', 'type' => 'media']]]));
    me(HttpException::class, fn () => $service->change($actor, 'media_library', 'delete_item', $payload, 'used-delete-001'));
    mc(count($service->items($actor, $module, ['usage' => 'used'])) === 1, 'Usage filter detects draft.');
    $published = $content->transition($actor, $cid, 'published', $draft['content']['version'], 'media-publish-001');
    $emptyDraft = $content->updateFields($actor, $cid, ['blocks' => []], 'media-clear-draft-001');
    me(HttpException::class, fn () => $service->change($actor, 'media_library', 'delete_item', $payload, 'published-delete-001'));
    $map = $service->publicMap($actor->siteId);
    mc(isset($map[$id]) && !isset($service->publicMap($other->siteId)[$id]), 'Public map isolation.');
    $content->archive($actor, $cid, 'media-archive-001');
    $before = $service->items($actor, $module)[0]; parse_str(parse_url($before['path'], PHP_URL_QUERY), $url);
    mc(is_file($service->publicImage($actor->siteId, $url['token'])), 'Current derivative served.');
    me(ContentNotFound::class, fn () => $service->publicImage($other->siteId, $url['token']));
    me(ContentNotFound::class, fn () => $service->publicImage($actor->siteId, '../../passwd'));
    $deleted = $service->change($actor, 'media_library', 'delete_item', $payload, 'unused-delete-001');
    mc($deleted === $service->change($actor, 'media_library', 'delete_item', $payload, 'unused-delete-001'), 'Delete retry identical.');
    mc(is_file($storage->path($original)), 'Original remains until committed maintenance.');
    me(ContentNotFound::class, fn () => $service->publicImage($actor->siteId, $url['token']));
    me(InvalidArgumentException::class, fn () => $content->createDraft($actor, 'page', ['title' => 'Deleted', 'slug' => 'media-deleted', 'blocks' => [['type' => 'image', 'media_id' => $id]]], 'media-deleted-draft-001'));
    // Simulate an interrupted removal: one derivative is already gone, the original temporarily cannot be unlinked.
    unlink($storage->path($original)); mkdir($storage->path($original));
    mc($service->collectGarbage() === 0, 'Failed unlink leaves resumable deleting state.');
    rmdir($storage->path($original));
    mc($service->collectGarbage() === 1, 'Maintenance resumes after partial deletion.');
    mc($service->collectGarbage() === 0, 'Maintenance idempotent.');
    mc(count($service->items($actor, $module)) === 0, 'Deleted item hidden.');
    $orphan = bin2hex(random_bytes(24)) . '.webp'; file_put_contents($storage->path($orphan), 'orphan'); touch($storage->path($orphan), time()-7200);
    $service->collectGarbage(); mc(!file_exists($storage->path($orphan)), 'Old orphan cleaned.');

    $auth = new class($actor) implements DeviceAuthenticator {
        public function __construct(private ActorContext $actor) {}
        public function authenticate(Request $request): ActorContext { return $this->actor; }
    };
    $kernel = new ApiKernel($auth, $read, changes: new PdoSiteChangeService($pdo, media: $service), media: $service);
    $fields['client_request_id'] = 'http-upload-001';
    $request = new Request('POST', '/v1/media', ['x-lepotager-protocol' => '1','content-type'=>'multipart/form-data; boundary=x'], '', $fields, ['media'=>$file]);
    $response = $kernel->handle($request);
    mc($response->status === 200, 'HTTP multipart route applies.');
    mc($kernel->handle($request)->payload === $response->payload, 'HTTP upload retry.');
    $pid = $response->payload['result']['id'];
    $change = new Request('POST','/v1/changes',['x-lepotager-protocol'=>'1','content-type'=>'application/json'],json_encode(['module_id'=>'media_library','action'=>'rotate_left','client_request_id'=>'http-change-001','payload'=>['item_id'=>$pid,'parent_id'=>'media_library']]));
    mc($kernel->handle($change)->status === 200, 'HTTP changes route delegates.');
    $get = new Request('GET','/v1/media',['x-lepotager-protocol'=>'1'],'', query:['module_id'=>'media_library','query'=>'Jardin','limit'=>'1']);
    mc(count($kernel->handle($get)->payload['items']) === 1, 'HTTP search route.');
    echo "Media MariaDB lifecycle, isolation, permissions and HTTP contracts passed.\n";
} finally {
    foreach (glob($root . '/*') ?: [] as $path) { if (is_file($path)) unlink($path); elseif (is_dir($path)) rmdir($path); }
    if (is_file($root.'/.media.lock')) unlink($root.'/.media.lock'); rmdir($root);
}
