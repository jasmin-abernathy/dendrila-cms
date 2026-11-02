<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
use Dendrila\Cms\Runtime\BrandImageStore;
use Dendrila\Cms\Http\UploadedFile;

function brandImageCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$root=dirname(__DIR__) . '/storage';
if (!is_dir($root)) mkdir($root,0700,true);
$fixture=tempnam(sys_get_temp_dir(),'dendrila-image-test-');
$image=imagecreatetruecolor(320,120);
imagealphablending($image,false);
imagesavealpha($image,true);
$transparent=imagecolorallocatealpha($image,0,0,0,127);
imagefill($image,0,0,$transparent);
$blue=imagecolorallocate($image,37,74,124);
imagefilledrectangle($image,12,12,300,100,$blue);
imagepng($image,$fixture);
imagedestroy($image);
$site='brand_test_'.bin2hex(random_bytes(5));
putenv('APP_BASE_URL=https://demo.example.test');
try {
    $result=BrandImageStore::upload($site,new UploadedFile($fixture,'logo.png'));
    brandImageCheck(str_starts_with($result['url'],'https://demo.example.test/brand-image.php?token='),
        'Public brand image URL uses the first-party HTTPS site.');
    $path=BrandImageStore::file($site,$result['token']);
    brandImageCheck(is_string($path) && is_file($path),'Private image exists.');
    brandImageCheck((fileperms($path)&0777)===0600,'No public filesystem access to original.');
    brandImageCheck((new finfo(FILEINFO_MIME_TYPE))->file($path)==='image/webp','Image is re-encoded to WebP.');
    brandImageCheck(@getimagesize($path)[0]===320,'Image keeps dimensions within limits.');
    brandImageCheck(BrandImageStore::file($site,'../../etc/passwd')===null,'Traversal token refused.');
    brandImageCheck(BrandImageStore::file('other_site',$result['token'])===null,'Other site cannot read image.');
    try {
        BrandImageStore::upload($site,new UploadedFile($fixture,'logo.svg',UPLOAD_ERR_FORM_SIZE));
        throw new RuntimeException('Invalid upload error accepted.');
    } catch (InvalidArgumentException $expected) {}
    BrandImageStore::discard($site,$result['token']);
    brandImageCheck(BrandImageStore::file($site,$result['token'])===null,'Failed / reverted upload can be discarded.');
    echo "Brand assets: private storage, bounded WebP, HTTPS route, site isolation and cleanup OK\n";
} finally {
    @unlink($fixture);
    $rootDir=$root.'/brand-assets/'.$site;
    if (is_dir($rootDir))@rmdir($rootDir);
}
