<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Auth\Totp;
use Dendrila\Cms\Installation\Catalog;
use Dendrila\Cms\BackOffice\BlockEditor;
function installCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
// RFC 6238 Appendix B independent known-answer vectors.
$secret='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
foreach([59=>'94287082',1111111109=>'07081804',1111111111=>'14050471',1234567890=>'89005924',2000000000=>'69279037',20000000000=>'65353130'] as $time=>$expected)installCheck(Totp::code($secret,intdiv($time,30),8)===$expected,'RFC TOTP vector');
installCheck(Totp::verify($secret,Totp::code($secret,100),99,3000)===100,'Valid step');
installCheck(Totp::verify($secret,Totp::code($secret,100),100,3000)===null,'Replay rejected');
installCheck(count(Catalog::themes())===6,'Six themes are installable');
installCheck(!in_array('gd',Catalog::requirements([])['extensions'],true),'No image requirement without module');
installCheck(in_array('gd',Catalog::requirements(['media'])['extensions'],true),'Media requirements resolved');
try{Catalog::selection(['unknown']);throw new RuntimeException('Unknown addon accepted');}catch(InvalidArgumentException){}
$blocks=[['type'=>'text','text'=>'Original'],['type'=>'gallery','media_ids'=>['photo']],['type'=>'image','media_id'=>'photo','shape'=>'arch','focal_x'=>20]];
$updated=BlockEditor::update($blocks,[0=>['text'=>'Edited'],1=>['media_ids'=>['']]],['photo']);
installCheck($updated[0]['text']==='Edited'&&$updated[1]['media_ids']===[]&&$updated[2]===$blocks[2],'Block updates preserve unrelated structure');
try{BlockEditor::update($blocks,[1=>['media_ids'=>['foreign']]],['photo']);throw new RuntimeException('Foreign photo accepted');}catch(InvalidArgumentException){}
echo "Installer catalog, RFC TOTP and block editor contracts passed.\n";
