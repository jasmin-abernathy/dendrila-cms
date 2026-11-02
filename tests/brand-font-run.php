<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Runtime\BrandFontStore;
use Dendrila\Cms\Http\UploadedFile;
function fontCheck(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
$root=dirname(__DIR__).'/storage';
if(!is_dir($root))mkdir($root,0700,true);
$path=tempnam(sys_get_temp_dir(),'dendrila-font-');
$site='font_test_'.bin2hex(random_bytes(5));
try{
    // Minimal SFNT test data exercises signature/size checks. No actual font
    // is served in CI; real rendering is covered by browser CSS tests.
    $header="OTTO".pack('n',1).str_repeat("\0",42);
    file_put_contents($path,$header);
    $ref=BrandFontStore::upload($site,new UploadedFile($path,'example.otf'));
    fontCheck((bool)preg_match('/^opentype:[a-f0-9]{48}$/D',$ref),'Generated reference is restricted.');
    $found=BrandFontStore::file($site,$ref);
    fontCheck($found!==null&&$found['mime']==='font/otf','Private file format resolved.');
    fontCheck((fileperms($found['path'])&0777)===0600,'Stored file stays private.');
    fontCheck(BrandFontStore::file('other-site',$ref)===null,'Font isolation between sites.');
    fontCheck(BrandFontStore::file($site,'../../secret')===null,'Unsafe reference denied.');
    file_put_contents($path,'<?php echo "evil"; ?>');
    try{BrandFontStore::upload($site,new UploadedFile($path,'evil.otf'));
        throw new RuntimeException('Non-font accepted.');}catch(InvalidArgumentException){}
    BrandFontStore::discard($site,$ref);
    fontCheck(BrandFontStore::file($site,$ref)===null,'Orphan font cleanup.');
    echo "Brand fonts: private upload, strict formats, path protection and cleanup OK\n";
}finally{
    @unlink($path);
    @rmdir($root.'/brand-fonts/'.$site);
}
