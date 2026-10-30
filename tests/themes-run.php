<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Dendrila\Cms\Runtime\Presentation;
use Dendrila\Cms\Front\PublicPageRenderer;
use Dendrila\Cms\Installation\InstallThemePreview;
function themeCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$root=dirname(__DIR__);$bundle=$root.'/public/design-kits';
$lock=json_decode(file_get_contents($bundle.'/bundle-lock.json'),true,512,JSON_THROW_ON_ERROR);
themeCheck(preg_match('/^[a-f0-9]{40}$/D',$lock['revision'])===1,'Bundle pins an upstream revision');
foreach($lock['files'] as $path=>$hash)themeCheck(hash_file('sha256',$bundle.'/'.$path)===$hash,'Bundled asset matches lock: '.$path);
$styles=['simple-illustrated'=>'simple-illustrated','quiet-elegant'=>'quiet-elegant','image-forward'=>'image-centered','ultra-minimal'=>'very-minimal','graphic-rhythm'=>'dynamic-graphic','editorial'=>'editorial'];
foreach($styles as $style=>$theme){
    $empty=InstallThemePreview::page('empty');
    $full=InstallThemePreview::page('demo');
    themeCheck($empty['blocks'] === [['type'=>'text','text'=>'Bienvenue sur notre site.']],'Blank installation has exactly the starter text');
    themeCheck(count($full['blocks']) > count($empty['blocks']),'Rich demo never becomes the installed default');
    themeCheck(InstallThemePreview::media('empty') === [],'Blank preview needs no image module or storage');
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
try{InstallThemePreview::page('unknown');throw new RuntimeException('Unknown preview mode accepted');}catch(InvalidArgumentException){} 
foreach(['../editorial','missing'] as $id){try{Presentation::forTheme($id,[]);throw new RuntimeException('Unknown style accepted');}catch(InvalidArgumentException){}}
try{Presentation::forTheme('editorial',['primary'=>'red; background:url(https://evil.test)']);throw new RuntimeException('CSS injection accepted');}catch(InvalidArgumentException){}
try{Presentation::forTheme('editorial',['logo_url'=>'javascript:alert(1)']);throw new RuntimeException('Unsafe logo accepted');}catch(InvalidArgumentException){}
echo "Six real design kits: mappings, pinned assets, rendering, brand and input validation passed.\n";
