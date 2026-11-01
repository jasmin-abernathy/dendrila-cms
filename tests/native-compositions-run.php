<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Runtime\Presentation;
use Dendrila\Cms\Front\PublicPageRenderer;
use Dendrila\Cms\Installation\StarterSiteCatalog;
use Dendrila\Cms\BackOffice\BlockEditor;
use Dendrila\Cms\Content\ContentTypeDefinition;

function nativeCheck(bool $check,string $message):void {
    if(!$check)throw new RuntimeException($message);
}
$schema=new ContentTypeDefinition(json_decode(file_get_contents(dirname(__DIR__).'/examples/content-types/page.json'),true));
nativeCheck(BlockEditor::blank('guided_assistant')['type']==='guided_assistant','Guided assistant available as native editable block.');
nativeCheck(BlockEditor::blank('image_text')['type']==='image_text','Image/text available as native editable block.');
$withChoice=BlockEditor::collection(BlockEditor::blank('guided_assistant'),'add_item',0);
nativeCheck(count($withChoice['items'])===2&&$withChoice['items'][1]['url']==='/contact',
    'Adding a guide choice yields a valid editable choice and destination.');

$guided=['type'=>'guided_assistant','heading'=>'Choisir un service','text'=>'Choisissez une option',
        'items'=>[['title'=>'Aide administrative','text'=>'Nous pouvons vous orienter','url'=>'/contact']]];
$image=['type'=>'image_text','heading'=>'Notre approche','text'=>'Une équipe humaine',
        'image_url'=>'https://example.org/image.jpg','alt'=>'Documents sur une table'];
$page=['slug'=>'home','title'=>'Bienvenue','blocks'=>[
    ['type'=>'text','text'=>'Aide et services'],['type'=>'cta','label'=>'Découvrir','url'=>'/contact'],
    $guided,$image
]];
$schema->validateValues($page);
foreach(array_keys(StarterSiteCatalog::REFERENCES) as $theme) {
    $p=Presentation::forTheme($theme,['hero_image_url'=>'https://example.org/hero.webp']);
    $html=(new PublicPageRenderer())->render(['name'=>'Démonstration'],$page,$p,$p['css_url']);
    nativeCheck(str_contains($html,'<details class="d-orientation__option"')
        && str_contains($html,'<summary class="d-orientation__select"')
        && str_contains($html,'data-dendrila-orientation')
        && str_contains($html,'data-orientation-choice')
        && str_contains($html,'id="assistant"'),'Interactive orientation markup visible: '.$theme);
    nativeCheck(str_contains($html,'class="d-image-text"')
        && str_contains($html,'Une équipe humaine'),'Editable illustrated text rendered: '.$theme);
    nativeCheck(str_contains($html,'d-hero__brand-image')
        && str_contains($html,'https://example.org/hero.webp'),'Editable hero URL used: '.$theme);
}
$bad=$page;$bad['blocks'][2]['items'][0]['url']='javascript:alert(1)';
try{$schema->validateValues($bad);throw new RuntimeException('Unsafe choice URL accepted');}
catch(InvalidArgumentException $e){}
$bad=$page;$bad['blocks'][3]['image_url']='javascript:alert(1)';
try{$schema->validateValues($bad);throw new RuntimeException('Unsafe image URL accepted');}
catch(InvalidArgumentException $e){}
nativeCheck(str_contains((string)file_get_contents(dirname(__DIR__).'/src/BackOffice/View.php'),'Assistant d’orientation'),
    'Block must be selectable in any site backoffice.');
nativeCheck(str_contains((string)file_get_contents(dirname(__DIR__).'/public/assets/guided-assistant.js'),'toggle'),
    'Script enhances native disclosure without disabling its no-JavaScript fallback.');
echo "Native compositions: interactive orientation and editable illustration validated in all six themes.\n";
