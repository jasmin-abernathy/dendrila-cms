<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Runtime\CustomFrontendBridge;
function customCheck(bool $v,string $msg):void{if(!$v)throw new RuntimeException($msg);}
customCheck(CustomFrontendBridge::escape('<div class="x">&')==='&lt;div class=&quot;x&quot;&gt;&amp;','Escapes HTML and attributes.');
customCheck(CustomFrontendBridge::href('/contact?topic=services&mode=1')==='/contact?topic=services&amp;mode=1','Internal URL escaped.');
foreach(['javascript:alert(1)','//evil.example','http://example.test','/evil path'] as $bad){
 try{CustomFrontendBridge::href($bad);throw new RuntimeException('Unsafe link accepted: '.$bad);}
 catch(InvalidArgumentException $expected){}
}
$pdo=new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE contents (site_id TEXT,content_id TEXT,published_values_json TEXT,published_slug TEXT,published_revision_id TEXT,status TEXT,type_id TEXT)");
$site='testing';
$published=['title'=>'Page extérieure','slug'=>'home','blocks'=>[
 ['type'=>'text','text'=>'Texte administré <script>alert(1)</script>'],
 ['type'=>'cards','heading'=>'Prestations','items'=>[['title'=>'Gestion de dossiers','text'=>'Suivi et classement']]],
 ['type'=>'cards','heading'=>'Prestations','items'=>[['title'=>'Autre','text'=>'Offre supplémentaire']]],
]];
$q=$pdo->prepare('INSERT INTO contents VALUES (?,?,?,?,?,?,?)');
$q->execute([$site,'page1',json_encode($published,JSON_THROW_ON_ERROR),'home','rev-1','published','page']);
$q->execute([$site,'page2',json_encode($published,JSON_THROW_ON_ERROR),'draft-only',null,'draft','page']);
$q->execute(['other','page3',json_encode($published,JSON_THROW_ON_ERROR),'other','rev-1','published','page']);
$bridge=new CustomFrontendBridge($pdo,$site);
$page=$bridge->page('/');
customCheck($page!==null && $page['title']==='Page extérieure','Published site content available without a theme.');
customCheck($bridge->page('draft-only')===null,'Private draft never leaked.');
customCheck($bridge->page('other')===null,'Other site never leaked.');
$card=$bridge->component($page,'cards','Prestations');
customCheck($card['items'][0]['title']==='Gestion de dossiers','Developer can render an arbitrary card composition.');
$next=$bridge->component($page,'cards','Prestations',1);
customCheck($next['items'][0]['title']==='Autre','Repeated components are addressable.');
customCheck($bridge->component($page,'gallery')===null,'Missing component is explicit.');
customCheck(!str_contains(CustomFrontendBridge::escape($page['blocks'][0]['text']),'<script>'),'Content must be escaped by templates.');
echo "Custom frontend: published site isolation, component selection and output escaping OK\n";
