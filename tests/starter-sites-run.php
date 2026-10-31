<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
require_once __DIR__.'/Support/InMemoryCmsStore.php';
use Dendrila\Cms\Installation\StarterSiteCatalog;
use Dendrila\Cms\Installation\InstallThemePreview;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Navigation\NavigationDefinition;
use Dendrila\Cms\Runtime\Presentation;
use Dendrila\Cms\Front\PublicPageRenderer;
use Dendrila\Cms\Tests\Support\InMemoryCmsStore;

function assertStarter(bool $condition,string $message):void {
 if (!$condition) throw new RuntimeException($message);
}
$required=[
 'simple-illustrated'=>['home','services','a-propos','informations','galerie','questions','contact'],
 'quiet-elegant'=>['home','presentation','espaces','informations','decouvrir','contact'],
 'image-forward'=>['home','galerie','projets','actualites','a-propos','contact'],
 'ultra-minimal'=>['home','services','methode','a-propos','offres','ressources','contact'],
 'graphic-rhythm'=>['home','agenda','activites','a-propos','partenaires','infos-pratiques','contact'],
 'editorial'=>['home','publications','dossiers','themes','rencontres','a-propos','contact'],
];
$photographyPages=StarterSiteCatalog::validatedPages('image-forward');
$galleryPages=array_values(array_filter($photographyPages,static fn(array $page):bool=>$page['slug']==='galerie'));
assertStarter(count($galleryPages)===1 && $galleryPages[0]['menu_label']==='Galerie',
    'Photo theme must expose one generic Galerie menu entry');
assertStarter(count($photographyPages)===6
    && !in_array('coulisses', array_column($photographyPages,'slug'), true)
    && !in_array('stenope', array_column($photographyPages,'slug'), true),
    'Do not impose behind-the-scenes or pinhole pages');
assertStarter(!str_contains(json_encode($photographyPages,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'sténopé'),
    'A specialist technique cannot be imposed in a generic photographic starter');
assertStarter(array_column($photographyPages,'menu_label')===
    ['Accueil','Galerie','Projets','Actualités','À propos','Contact'],
    'Photographic site should use generic navigation labels');

$blocksTotal=0;
$pagesTotal=0;
foreach($required as $style=>$expected) {
 $pages=StarterSiteCatalog::validatedPages($style);
 assertStarter(array_column($pages,'slug')===$expected,$style.' page inventory differs');
 $menu=NavigationDefinition::normalizeItems(StarterSiteCatalog::navigation($style));
 assertStarter(count($menu)===count($pages),'Every seeded page has a navigation entry');
 $allSlugs=array_column($pages,'slug');
 foreach ($pages as $page) {
   foreach ($page['blocks'] as $block) {
     $links=[];
     if ($block['type']==='cta') $links[]=($block['url']??'');
     if ($block['type']==='cards') foreach (($block['items']??[]) as $item) $links[]=($item['url']??'');
     foreach ($links as $url) {
       if ($url==='' || !str_starts_with((string)$url,'/')) continue;
       $target=trim((string)parse_url($url,PHP_URL_PATH),'/') ?: 'home';
       assertStarter(in_array($target,$allSlugs,true),
         'Starter link in '.$style.'/'.$page['slug'].' points to missing '.$target);
     }
   }
 }

 $store=new InMemoryCmsStore();
 $site='starter-'.$style;
 $actor=new ActorContext($site,'owner');
 $store->types[$site]['page']=json_decode(
  (string)file_get_contents(dirname(__DIR__).'/examples/content-types/page.json'),
  true,512,JSON_THROW_ON_ERROR
 );
 $store->permissions[$site]['owner']=[
  'content.create','content.read','content.read_draft','content.update',
  'content.publish','content.publish_without_review'
 ];
 $service=new ContentService($store,new AuthorizationService($store));
 $renderer=new PublicPageRenderer();
 $presentation=Presentation::forTheme($style);
 foreach($pages as $page) {
  $slug=$page['slug'];
  assertStarter(count($page['blocks'])>=1,'Empty starter page');
  $values=['title'=>$page['title'],'slug'=>$slug,'blocks'=>$page['blocks']];
  $created=$service->createDraft($actor,'page',$values,'install-starter-'.$slug);
  assertStarter($created['content']['version']===1,'Created page version');
  if($slug==='home') {
   $published=$service->transition($actor,$created['content']['id'],'published',1,'install-publish-home');
   assertStarter($published['content']['status']==='published','Home page must be public');
  } else {
   assertStarter($service->get($actor,$created['content']['id'])['status']==='draft','Secondary pages must stay draft');
  }
  // Every bundled page must render without a paid module, database or media upload.
  $html=$renderer->render(['name'=>'Site de départ','home_url'=>'/'],
   $values,$presentation,$presentation['css_url'],[],array_map(static fn($x):array=>[
     'label'=>$x['label'],'url'=>$x['url']
   ],$menu));
  assertStarter(str_contains($html,'data-dendrila-theme='),'Every page uses real theme rendering');
  assertStarter(!str_contains($html,'javascript:'),'No untrusted script URLs');
  if ($slug==='home' && in_array($style,['simple-illustrated','quiet-elegant','image-forward','graphic-rhythm'],true)) {
   assertStarter(str_contains($html,'class="d-hero__art"'),'Theme-supplied artwork missing: '.$style);
   if ($style==='image-forward') {
    assertStarter(substr_count($html,'/assets/install-demo/')===3,
      'Photo-first homepage must have a real locally bundled triptych');
   }
  }
  if ($slug!=='home') {
   assertStarter(!str_contains($html,'class="d-hero__art"'),'Artwork repeated on interior pages: '.$style);
  }
  $blocksTotal+=count($page['blocks']);
  $pagesTotal++;
 }
 assertStarter(count($store->contents[$site])===count($pages),'All starter pages created');
 $preview=InstallThemePreview::page('empty',$style);
 assertStarter($preview['blocks']===$pages[0]['blocks'],'Installer preview and seeded homepage match');
 $renderedNav=InstallThemePreview::navigation('empty',$style);
 assertStarter(count($renderedNav)===count($pages),'All starter routes previewable');
}
assertStarter($pagesTotal===40&&$blocksTotal===97,'The six starters contain exactly 40 editable pages and 97 sections');
echo "Starter sites: $pagesTotal CMS pages, $blocksTotal sections, six styles; draft/public states, links and renders OK\n";

