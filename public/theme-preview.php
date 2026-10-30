<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use Dendrila\Cms\Runtime\Presentation;
use Dendrila\Cms\Front\PublicPageRenderer;
$id=is_string($_GET['theme']??null)?$_GET['theme']:'';
try{$presentation=Presentation::forTheme($id);}catch(InvalidArgumentException){http_response_code(404);exit;}
header('Content-Type: text/html; charset=utf-8');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline'; img-src 'self'; frame-ancestors 'self'; base-uri 'none'");
echo (new PublicPageRenderer())->render(['name'=>'Atelier des possibles'],['title'=>'Des idées qui prennent vie','blocks'=>[
 ['type'=>'text','text'=>'Un lieu pour créer, partager et découvrir.'],['type'=>'image','media_id'=>'preview','alt'=>'Paysage illustré'],
 ['type'=>'cta','label'=>'Découvrir','url'=>'#contenu'],['type'=>'cards','items'=>[['title'=>'Rencontrer','text'=>'Des projets qui nous rassemblent.'],['title'=>'Imaginer','text'=>'Une autre façon de faire.']]],
]],$presentation,'/assets/site.css',['preview'=>'/assets/preview.svg'],[['label'=>'Accueil','url'=>'/'],['label'=>'Nos projets','url'=>'#contenu']]);
