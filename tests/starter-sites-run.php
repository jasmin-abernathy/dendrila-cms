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
  if ($slug==='home' && in_array($style,['simple-illustrated','quiet-elegant','image-forward','graphic-rhythm','editorial'],true)) {
   assertStarter(str_contains($html,'class="d-hero__art"'),'Theme-supplied artwork missing: '.$style);
   if ($style==='image-forward') {
    assertStarter(substr_count($html,'/design-kits/themes/image-centered/assets/')===3,
      'Photo-first homepage must have a local CC0 triptych');
   }
  }
  if ($slug!=='home') {
   assertStarter(!str_contains($html,'class="d-hero__art"'),'Artwork repeated on interior pages: '.$style);
  }
  if ($slug==='home' && count($page['blocks'])>3) {
   assertStarter(str_contains($html,'class="d-home-composition d-home-composition--'),
      'Homepage must compose sections as a visual layout: '.$style);
  }
  if ($slug==='contact') {
   $formBlocks=array_values(array_filter($page['blocks'],
       static fn(array $block):bool=>($block['type']??null)==='contact_form'));
   assertStarter(count($formBlocks)===1,
      'Every contact page is seeded with one editable native form: '.$style);
   foreach(['class="d-contact-form"','name="email"','name="subject"',
            'name="message"','type="submit" disabled',
            'Formulaire de contact non encore configuré'] as $marker) {
      assertStarter(str_contains($html,$marker),
         'Contact page lacks form markup '.$marker.' in '.$style);
   }
  }
  if ($slug==='home') {
   $ctaBlocks=array_values(array_filter($page['blocks'],
       static fn(array $block):bool=>($block['type']??null)==='cta'));
   assertStarter(count($ctaBlocks)===($style==='ultra-minimal'?3:2),
       'Every homepage has a hero CTA and a visible concluding CTA; very-minimal has two hero actions: '.$style);
   assertStarter(($page['blocks'][count($page['blocks'])-1]['type']??null)==='cta',
       'Concluding CTA must be the final editable section: '.$style);
   assertStarter(str_contains($html,'class="d-home-tile d-home-tile--cta"'),
       'Concluding CTA is rendered in the visual homepage layout: '.$style);
   assertStarter(str_contains($html,'href="'.$ctaBlocks[1]['url'].'"'),
       'Final CTA destination must be present in real HTML: '.$style);
  }
  $blocksTotal+=count($page['blocks']);
  $pagesTotal++;
 }
 assertStarter(count($store->contents[$site])===count($pages),'All starter pages created');
 $preview=InstallThemePreview::page('empty',$style);
 assertStarter($preview['blocks']===StarterSiteCatalog::installationPages($style)[0]['blocks'],'Installer preview matches the default non-guided installation');
 $renderedNav=InstallThemePreview::navigation('empty',$style);
 assertStarter(count($renderedNav)===count($pages),'All starter routes previewable');
}
$defaultPages=StarterSiteCatalog::installationPages('ultra-minimal');
$guidedPages=StarterSiteCatalog::installationPages('ultra-minimal',true,'compact');
$otherGuided=StarterSiteCatalog::installationPages('editorial',true,'list');
$guideCount=static fn(array $blocks):int=>count(array_filter($blocks,
    static fn(array $block):bool=>($block['type']??null)==='guided_assistant'));
assertStarter($guideCount($defaultPages[0]['blocks'])===0,
    'An installation without opt-in cannot contain a guided assistant');
assertStarter($guideCount($guidedPages[0]['blocks'])===1,
    'The professional theme can install the chosen guide');
assertStarter($guideCount($otherGuided[0]['blocks'])===1,
    'The editorial theme can also install the same optional module');
assertStarter(count(array_filter($defaultPages[0]['blocks'],
    static fn(array $block):bool=>($block['url']??'')==='/#assistant'))===0,
    'Disabled guide must not leave a broken shortcut');
$guidedBlock=array_values(array_filter($guidedPages[0]['blocks'],
    static fn(array $block):bool=>($block['type']??null)==='guided_assistant'))[0];
assertStarter(($guidedBlock['presentation']??'')==='compact',
    'Guided presentation survives installation composition');
try {StarterSiteCatalog::installationPages('editorial',true,'invalid');
    throw new RuntimeException('Invalid guided layout accepted');
} catch(InvalidArgumentException $e){}
assertStarter($pagesTotal===40&&$blocksTotal===116,'The six starters contain exactly 40 editable pages and 116 sections');
echo "Starter sites: $pagesTotal CMS pages, $blocksTotal sections, six styles; draft/public states, links and renders OK\n";

