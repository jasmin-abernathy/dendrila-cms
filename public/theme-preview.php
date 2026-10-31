<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';

use Dendrila\Cms\Installation\InstallThemePreview;
use Dendrila\Cms\Runtime\Presentation;
use Dendrila\Cms\Front\PublicPageRenderer;

$id=is_string($_GET['theme']??null)?$_GET['theme']:'';
$mode=is_string($_GET['mode']??null)?$_GET['mode']:'demo';
if(!in_array($mode,['empty','demo'],true)){http_response_code(404);exit('Aperçu inconnu.');}
try {
    // Both previews use the actual theme resolver and public HTML renderer.
    // No database or installed site, admin session or optional module required.
    $presentation=Presentation::forTheme($id);
    $previewPage=InstallThemePreview::page($mode,$id);
    if($mode==='empty'){
        $requested=is_string($_GET['page']??null)?$_GET['page']:'home';
        $found=false;
        foreach(\Dendrila\Cms\Installation\StarterSiteCatalog::validatedPages($id) as $candidate){
            if($candidate['slug']!==$requested) continue;
            $previewPage=['title'=>$candidate['title'],'slug'=>$candidate['slug'],'blocks'=>$candidate['blocks']];
            $found=true;
            break;
        }
        if(!$found) throw new InvalidArgumentException('Unknown starter page.');
        // In a read-only installer preview, internal CTA and card links must
        // navigate the other draft pages inside the preview, not 404 on
        // routes that do not exist until installation.
        $slugs=array_column(\Dendrila\Cms\Installation\StarterSiteCatalog::validatedPages($id),'slug');
        $previewLink=static function(string $url) use ($id,$slugs):string {
            if(!str_starts_with($url,'/') || str_starts_with($url,'//')) return $url;
            $slug=trim((string)parse_url($url,PHP_URL_PATH),'/');
            $slug=$slug===''?'home':$slug;
            if(!in_array($slug,$slugs,true)) return $url;
            return '/theme-preview.php?theme='.rawurlencode($id).'&mode=empty&page='.rawurlencode($slug);
        };
        foreach($previewPage['blocks'] as &$block){
            if(($block['type']??'')==='cta' && is_string($block['url']??null)) {
                $block['url']=$previewLink($block['url']);
            }
            if(($block['type']??'')==='cards' && is_array($block['items']??null)){
                foreach($block['items'] as &$item){
                    if(is_string($item['url']??null)) $item['url']=$previewLink($item['url']);
                }
                unset($item);
            }
        }
        unset($block);
    }
    $html=(new PublicPageRenderer())->render(
        InstallThemePreview::site($mode,$id),
        $previewPage,
        $presentation,
        $presentation['css_url'],
        InstallThemePreview::media($mode),
        InstallThemePreview::navigation($mode,$id),
        mediaAlts: InstallThemePreview::alternatives()
    );
} catch (InvalidArgumentException) {
    http_response_code(404);
    exit('Thème inconnu.');
}
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Robots-Tag: noindex, nofollow');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; frame-ancestors 'self'; base-uri 'none'; form-action 'none'");
echo $html;

