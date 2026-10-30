<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Dendrila\Cms\Media\ImageStorage;
use Dendrila\Cms\Media\MediaReferences;
use Dendrila\Cms\Http\UploadedFile;
use Dendrila\Cms\Http\Request;
use Dendrila\Cms\Api\PdoSiteReadModel;
use Dendrila\Cms\Api\MediaLibraryModule;

function mediaCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function mediaReject(callable $fn): void {
    try { $fn(); } catch (InvalidArgumentException|RuntimeException $e) { return; }
    throw new RuntimeException('Expected rejection.');
}
$root = sys_get_temp_dir() . '/dendrila-media-' . bin2hex(random_bytes(8));
mkdir($root, 0700);
try {
    $storage = new ImageStorage($root);
    $path = $root . '/upload.png';
    $im = imagecreatetruecolor(40, 20);
    imagefilledrectangle($im, 0, 0, 39, 19, imagecolorallocate($im, 20, 150, 80));
    imagepng($im, $path);
    $file = new UploadedFile($path, '../../photo.php');
    $info = $storage->inspect($file);
    mediaCheck($info['mime'] === 'image/png' && $info['width'] === 40, 'Real MIME and dimensions.');
    $key = $storage->original($file);
    $hash = hash_file('sha256', $storage->path($key));
    $variants = $storage->variants($storage->path($key), 90);
    mediaCheck($variants['web']['width'] === 20 && $variants['web']['height'] === 40, 'Rotation changes derived dimensions.');
    mediaCheck(hash_file('sha256', $storage->path($key)) === $hash, 'Original preserved.');
    foreach ($variants as $v) {
        mediaCheck(getimagesize($storage->path($v['storage_key']))['mime'] === 'image/webp', 'Re-encoded WebP.');
        mediaCheck(str_starts_with($storage->path($v['storage_key']), $root . '/'), 'Controlled paths.');
    }
    mediaReject(fn () => $storage->path('../../passwd'));
    mediaReject(fn () => $storage->inspect(new UploadedFile($path, 'x', UPLOAD_ERR_PARTIAL)));
    mediaReject(fn () => (new ImageStorage($root, 10))->inspect($file));
    mediaReject(fn () => (new ImageStorage($root, maxDimension: 30))->inspect($file));
    mediaReject(fn () => (new ImageStorage($root, maxPixels: 100))->inspect($file));
    file_put_contents($root . '/fake.jpg', '<?php echo "not an image";');
    mediaReject(fn () => $storage->inspect(new UploadedFile($root . '/fake.jpg', 'image.jpg')));
    file_put_contents($root . '/bad.png', substr(file_get_contents($path), 0, 33));
    mediaReject(fn () => $storage->variants($root . '/bad.png', 0));
    file_put_contents($root . '/vector.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
    mediaReject(fn () => $storage->inspect(new UploadedFile($root . '/vector.svg', 'image.png')));
    $module = MediaLibraryModule::definition();
    mediaCheck(PdoSiteReadModel::safeModules([$module])[0]['kind'] === 'media_library', 'Module accepted.');
    $emptyMedia = $module; $emptyMedia['media'] = [];
    mediaCheck(PdoSiteReadModel::safeModules([$emptyMedia])[0]['media'] instanceof stdClass, 'Empty optional media config remains an object.');
    foreach (['allow_rotate', 'allow_delete'] as $option) {
        $bad = $module; $bad['options'][$option] = 'true';
        mediaReject(fn () => PdoSiteReadModel::safeModules([$bad]));
        $bad['options'][$option] = null;
        mediaReject(fn () => PdoSiteReadModel::safeModules([$bad]));
    }
    $defs = ['fields' => [['id' => 'cover', 'type' => 'media'], ['id' => 'blocks', 'type' => 'blocks'], ['id' => 'text', 'type' => 'text']]];
    $values = ['cover' => 'one', 'blocks' => [['type' => 'image', 'media_id' => 'two'], ['type' => 'gallery', 'media_ids' => ['three']],
        ['type' => 'cards', 'items' => [['media_id' => 'four']]]], 'text' => 'unrelated'];
    mediaCheck(MediaReferences::ids($defs, $values) === ['four', 'one', 'three', 'two'], 'All typed media references.');
    $request = new Request('POST', '/v1/media', ['content-type' => 'multipart/form-data; boundary=test'], '', [], ['media' => $file]);
    mediaCheck($request->uploadedMedia() === $file, 'Injectable trusted upload adapter.');
    mediaReject(fn () => UploadedFile::fromPhp(['error' => UPLOAD_ERR_OK, 'tmp_name' => $path, 'name' => 'fake']));
    echo "Media image, upload and reference contracts passed.\n";
} finally { foreach (glob($root . '/*') ?: [] as $path) unlink($path); rmdir($root); }
