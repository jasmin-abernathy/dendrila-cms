<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Runtime\Presentation;
use Dendrila\Cms\Front\PublicPageRenderer;
use Dendrila\Cms\Installation\InstallThemePreview;
function themeCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$root=dirname(__DIR__);$bundle=$root.'/public/design-kits';
$lock=json_decode(file_get_contents($bundle.'/bundle-lock.json'),true,512,JSON_THROW_ON_ERROR);
themeCheck((fileperms($bundle) & 0005) === 0005,
    'The published design-kit root must be traversable by the web server (0755, not mkdtemp 0700)');
themeCheck(preg_match('/^[a-f0-9]{40}$/D',$lock['revision'])===1,'Bundle pins an upstream revision');
foreach($lock['files'] as $path=>$hash)themeCheck(hash_file('sha256',$bundle.'/'.$path)===$hash,'Bundled asset matches lock: '.$path);
$structures=[];
$styles=['simple-illustrated'=>'simple-illustrated','quiet-elegant'=>'quiet-elegant','image-forward'=>'image-centered','ultra-minimal'=>'very-minimal','graphic-rhythm'=>'dynamic-graphic','editorial'=>'editorial'];
foreach($styles as $style=>$theme){
    $empty=InstallThemePreview::page('empty',$style);
    $full=InstallThemePreview::page('demo',$style);
    $starter=\Dendrila\Cms\Installation\StarterSiteCatalog::validatedPages($style);
    themeCheck($empty['blocks'] === $starter[0]['blocks'],'Starter preview matches real installation homepage');
    themeCheck(count($starter)>=6,'Every style seeds a complete multi-page website');
    themeCheck(count($empty['blocks'])>=3,'Homepage includes real reusable sections');
    themeCheck($full['blocks'] !== $empty['blocks'],'Photo-rich demo remains separate from starter');
    themeCheck(InstallThemePreview::media('empty') === [],'Starter pages require no image module or storage');
    $structures[]=implode(',',array_column($full['blocks'],'type'));
    $photos=InstallThemePreview::media('demo');
    themeCheck(count($photos)===4,'Four shared photos for every theme');
    foreach($photos as $url){
        themeCheck(str_starts_with($url,'/assets/install-demo/'),'No third-party tracking on preview');
        themeCheck(is_file($root.'/public'.$url),'The local WebP exists: '.$url);
        themeCheck(filesize($root.'/public'.$url) < 320000,'Photograph is compressed for light previews');
    }

    themeCheck(!preg_match('/blog|actualité|lorem ipsum/i',json_encode($full,JSON_UNESCAPED_UNICODE)), 'No automatic blog or filler feed');
    $p=Presentation::forTheme($style,[]);
    themeCheck($p['design_kit']['id']===$theme,'Preset maps to correct theme');
    themeCheck(str_starts_with($p['css_url'],'/design-kits/themes/'.$theme.'/theme.css?v='),'Real theme stylesheet is served');
    $css=file_get_contents($p['design_kit']['entrypoint_path']);
    preg_match_all('/url\(\s*[\'\"]?([^\)\'\"]+)/',$css,$matches);
    foreach($matches[1] as $asset){if(str_starts_with($asset,'data:')||str_starts_with($asset,'#'))continue;themeCheck(!str_contains($asset,'://')&&is_file($p['design_kit']['root_path'].'/'.$asset),'Theme asset is bundled locally: '.$asset);}
    $html=(new PublicPageRenderer())->render(['name'=>'Site test'],['title'=>'Une page','blocks'=>[['type'=>'text','text'=>'Contenu'],['type'=>'image','media_id'=>'photo','shape'=>'organic-soft']]],$p,$p['css_url'],['photo'=>'/assets/preview.svg']);
    themeCheck(str_contains($html,'data-dendrila-theme="'.$theme.'"')&&str_contains($html,$p['css_url']),'Rendered page uses matching CSS scope');
    themeCheck(!str_contains($html,'/assets/site.css'),'Native rendering no longer uses generic stylesheet');
    $branded=Presentation::forTheme($style,['primary'=>'#204060','body_font'=>'neutral-sans','logo_url'=>'https://site.example/logo.svg']);
    themeCheck(str_contains($branded['compiled_css'],'--dendrila-color-primary: #204060;'),'Brand overrides compile in the selected theme scope');
    themeCheck($branded['brand_assets']['logo_url']==='https://site.example/logo.svg','Logo is forwarded');
}
// Real landing compositions must be distinct before a customer uploads media.
$layoutContracts=[
 'simple-illustrated'=>['illustration-service.svg','d-card-grid>.d-card:first-child','d-section--block-steps'],
 'quiet-elegant'=>['d-hero__art img','d-card-grid .d-card:first-child','d-section--block-gallery'],
 'image-forward'=>['d-hero__art img','d-gallery__item:nth-child(1)','d-section--block-gallery'],
 'ultra-minimal'=>['d-card-grid .d-card','d-hero__copy .d-lead','d-section--block-pricing'],
 'graphic-rhythm'=>['d-hero__art::before','d-title','d-section--block-cards'],
 'editorial'=>['d-brand','d-section--block-cards .d-card:first-child','d-hero__copy .d-lead'],
];
foreach($layoutContracts as $design=>$features){
 $css=(string)file_get_contents(Presentation::forTheme($design)['design_kit']['entrypoint_path']);
 foreach($features as $feature)themeCheck(str_contains($css,$feature),'Missing distinct layout ' . $design . ': ' . $feature);
}
$artwork=$root.'/public/design-kits/themes/simple-illustrated/assets/';
foreach(['illustration-service.svg','illustration-process.svg','illustration-garden.svg'] as $filename){
 themeCheck(is_file($artwork.$filename)&&filesize($artwork.$filename)>700,'Actual local illustration is bundled: '.$filename);
}
foreach(['simple-illustrated','quiet-elegant','image-forward','graphic-rhythm'] as $design){
 $starter=\Dendrila\Cms\Installation\StarterSiteCatalog::validatedPages($design)[0];
 $presentation=Presentation::forTheme($design);
 $html=(new PublicPageRenderer())->render(['name'=>'Test starter'],[
    'title'=>$starter['title'],'slug'=>'home','blocks'=>$starter['blocks']
 ],$presentation,$presentation['css_url']);
 themeCheck(str_contains($html,'class="d-hero__art"'),'Home fallback is visible for ' . $design);
}
themeCheck(count(array_unique($structures))===6,'Six distinct demo content structures');
try{InstallThemePreview::page('unknown');throw new RuntimeException('Unknown preview mode accepted');}catch(InvalidArgumentException){} 
foreach(['../editorial','missing'] as $id){try{Presentation::forTheme($id,[]);throw new RuntimeException('Unknown style accepted');}catch(InvalidArgumentException){}}
try{Presentation::forTheme('editorial',['primary'=>'red; background:url(https://evil.test)']);throw new RuntimeException('CSS injection accepted');}catch(InvalidArgumentException){}
try{Presentation::forTheme('editorial',['logo_url'=>'javascript:alert(1)']);throw new RuntimeException('Unsafe logo accepted');}catch(InvalidArgumentException){}
echo "Six real design kits: mappings, pinned assets, rendering, brand and input validation passed.\n";

