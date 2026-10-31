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

