<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Forum\ForumPhotoStorage;
use Dendrila\Cms\Forum\ForumService;
use Dendrila\Cms\Storage\PdoCmsStore;

$dsn = getenv('DB_DSN') ?: '';
$username = getenv('DB_USERNAME') ?: '';
if ($dsn === '' || $username === '') {
    fwrite(STDOUT, "Forum PDO tests skipped (no DB_DSN).\n");
    exit(0);
}
$pdo = new PDO($dsn, $username, getenv('DB_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$private = sys_get_temp_dir() . '/dendrila-forum-test-' . bin2hex(random_bytes(6));
$storage = new ForumPhotoStorage($private);
$service = new ForumService($pdo, new AuthorizationService(new PdoCmsStore($pdo)), $storage);
$actor = new ActorContext('site-native', 'owner-native');

function forumPdoAssert(bool $test, string $message): void
{
    if (!$test) { throw new RuntimeException($message); }
}
$topic = $service->openTopic($actor, 'Un nouveau sujet photo', 'Bonjour les photographes !');
forumPdoAssert($topic['status'] === 'pending' && $service->visibleTopic(
    $actor->siteId, $topic['topic_id']) === null, 'New topics must stay private.');
$queue = $service->moderationQueue($actor);
forumPdoAssert(in_array($topic['topic_id'], array_column($queue['topics'], 'topic_id'), true),
    'Moderator can see pending threads.');
$service->moderateTopic($actor, $topic['topic_id'], true);
forumPdoAssert($service->visibleTopic($actor->siteId, $topic['topic_id']) !== null,
    'Approved topic becomes visible.');
forumPdoAssert(count($service->visibleMessages($actor->siteId, $topic['topic_id'])) === 1,
    'Opening message is approved together with its topic.');
$reply = $service->reply($actor, $topic['topic_id'], 'Voici mon cliché.');
forumPdoAssert(count($service->visibleMessages($actor->siteId, $topic['topic_id'])) === 1,
    'Pending reply is not publicly visible.');

if (!function_exists('imagepng') || !function_exists('imagewebp')) {
    throw new RuntimeException('Forum media integration needs the PHP GD extension.');
}
$img = imagecreatetruecolor(32, 32);
ob_start();
imagepng($img);
$image = ob_get_clean();
imagedestroy($img);
forumPdoAssert(is_string($image), 'Image fixture is generated.');
$photo = $service->attachPhoto($actor, $reply['message_id'], $image, 'Une photo de test');
forumPdoAssert($service->publicPhoto($actor->siteId, $photo['photo_id']) === null,
    'Pending image is not publicly downloadable.');
$service->moderateMessage($actor, $reply['message_id'], true);
$download = $service->publicPhoto($actor->siteId, $photo['photo_id']);
forumPdoAssert(is_string($download) && substr($download, 0, 4) === 'RIFF',
    'Approved image serves safe re-encoded WebP bytes.');
forumPdoAssert($service->publicPhoto('another-site', $photo['photo_id']) === null,
    'Images never leak between tenant sites.');
forumPdoAssert(count($service->visibleMessages($actor->siteId, $topic['topic_id'])) === 2,
    'Reply is visible only after moderation.');
$service->lockTopic($actor, $topic['topic_id'], true);
try {
    $service->reply($actor, $topic['topic_id'], 'Message refusé');
    throw new RuntimeException('Locked thread accepted a reply.');
} catch (DomainException) {
    // A closed conversation rejects new messages.
}
$service->moderateTopic($actor, $topic['topic_id'], false);
forumPdoAssert($service->publicPhoto($actor->siteId, $photo['photo_id']) === null,
    'Hiding a topic also hides its previously approved photos.');
echo "Forum MariaDB, moderation and WebP integration tests passed.\n";
