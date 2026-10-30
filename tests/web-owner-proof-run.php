<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Installation\WebOwnerProof;

function checkWebProof(bool $condition,string $message):void{
    if(!$condition)throw new RuntimeException($message);
}
$dir=sys_get_temp_dir().'/dendrila-web-proof-'.bin2hex(random_bytes(8));
mkdir($dir,0700,true);
try {
    $challenge=WebOwnerProof::createChallenge();
    checkWebProof((bool)preg_match('/^[a-f0-9]{48}$/D',$challenge),'Unpredictable challenge format');
    checkWebProof(!WebOwnerProof::confirm($dir,$challenge),'Cannot verify missing file');
    file_put_contents($dir.'/'.WebOwnerProof::FILENAME,'incorrect');
    checkWebProof(!WebOwnerProof::confirm($dir,$challenge),'Wrong proof rejected');
    checkWebProof(is_file($dir.'/'.WebOwnerProof::FILENAME),'Wrong proof not consumed');
    file_put_contents($dir.'/'.WebOwnerProof::FILENAME,$challenge."\n");
    checkWebProof(!WebOwnerProof::confirm($dir,'x'),'Invalid challenge rejected');
    checkWebProof(WebOwnerProof::confirm($dir,$challenge),'Matching file-manager proof accepted');
    checkWebProof(!file_exists($dir.'/'.WebOwnerProof::FILENAME),'Proof is consumed');
    checkWebProof(!WebOwnerProof::confirm($dir,$challenge),'Replay rejected');
    file_put_contents($dir.'/'.WebOwnerProof::FILENAME,str_repeat('0',129));
    checkWebProof(!WebOwnerProof::confirm($dir,$challenge),'Oversized proof rejected');
    unlink($dir.'/'.WebOwnerProof::FILENAME);
    file_put_contents($dir.'/local.php','<?php return [];');
    file_put_contents($dir.'/'.WebOwnerProof::FILENAME,$challenge);
    checkWebProof(!WebOwnerProof::confirm($dir,$challenge),'Installed site cannot reclaim ownership');
    unlink($dir.'/local.php');unlink($dir.'/'.WebOwnerProof::FILENAME);
    echo "Secure file-manager ownership proof: validity, replay, invalid data and installed-site protections passed.\n";
} finally {
    foreach(glob($dir.'/*')?:[] as $file)if(is_file($file)||is_link($file))@unlink($file);
    @rmdir($dir);
}
