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
    $legalPreviews=[
        'mentions-legales'=>[
            'title'=>'Mentions légales — exemple',
            'intro'=>'Page de démonstration : ce texte ne constitue pas des mentions légales prêtes à publier. Renseignez des informations exactes avant la mise en ligne.',
            'items'=>[
                ['label'=>'Éditeur du site','value'=>'Nom ou dénomination sociale, forme juridique et coordonnées à compléter.'],
                ['label'=>'Publication','value'=>'Responsable de la publication : à renseigner.'],
                ['label'=>'Hébergement','value'=>'Nom, adresse et coordonnées de l’hébergeur à renseigner.'],
                ['label'=>'Activité','value'=>'Précisions éventuelles selon la nature et les obligations de votre activité.'],
            ],
        ],
        'confidentialite'=>[
            'title'=>'Confidentialité — exemple',
            'intro'=>'Page de démonstration : adaptez la politique aux traitements réellement effectués par votre site. Ce modèle ne certifie aucune conformité.',
            'items'=>[
                ['label'=>'Responsable du traitement','value'=>'Identité et coordonnées à renseigner.'],
                ['label'=>'Finalités et bases légales','value'=>'Documentez les traitements réellement nécessaires au fonctionnement du site.'],
                ['label'=>'Durées et destinataires','value'=>'Précisez la conservation et les sous-traitants éventuels.'],
                ['label'=>'Droits des personnes','value'=>'Indiquez un contact pour les demandes d’accès, de rectification ou d’effacement.'],
            ],
        ],
        'cookies'=>[
            'title'=>'Cookies et traceurs — exemple',
            'intro'=>'Page de démonstration : décrivez vos cookies et services tiers après inventaire. N’annoncez aucun recueil de consentement qui ne serait pas activé.',
            'items'=>[
                ['label'=>'Cookies nécessaires','value'=>'Indiquez les cookies techniques réellement utilisés.'],
                ['label'=>'Services facultatifs','value'=>'Décrivez les mesures d’audience, médias externes et autres traceurs éventuels.'],
                ['label'=>'Consentement','value'=>'Précisez comment recueillir, retirer ou modifier les choix, si des services concernés sont activés.'],
            ],
        ],
    ];
    if($mode==='empty'){
        $requested=is_string($_GET['page']??null)?$_GET['page']:'home';
        $found=false;
        if(isset($legalPreviews[$requested])){
            $example=$legalPreviews[$requested];
            $previewPage=['title'=>$example['title'],'slug'=>$requested,'blocks'=>[
                ['type'=>'text','text'=>$example['intro']],
                ['type'=>'key_values','heading'=>'À personnaliser avant publication','items'=>$example['items']],
            ]];
            $found=true;
        } else {
            foreach(\Dendrila\Cms\Installation\StarterSiteCatalog::validatedPages($id) as $candidate){
                if($candidate['slug']!==$requested) continue;
                $previewPage=['title'=>$candidate['title'],'slug'=>$candidate['slug'],'blocks'=>$candidate['blocks']];
                $found=true;
                break;
            }
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
    // Populate only READ-ONLY previews with documented CC0/public-domain
    // photographs. These IDs never go into the installed CMS database.
    $previewPhotos = [];
    $previewAlts = [];
    if ($mode === 'empty') {
        $photoFiles = match ($id) {
            'simple-illustrated' => ['community', 'flowers', 'nature'],
            'quiet-elegant' => ['forest', 'architecture'],
            'image-forward' => ['mountains', 'architecture', 'forest', 'nature', 'flowers'],
            'graphic-rhythm' => ['workshop', 'community'],
            'editorial' => ['workshop', 'nature'],
            default => [],
        };
        foreach ($photoFiles as $photoName) {
            $photoId = 'demo_' . $photoName;
            $previewPhotos[$photoId] = '/assets/install-demo/' . $photoName . '.webp';
            $previewAlts[$photoId] = [
                'community' => 'Personnes autour d’une table',
                'flowers' => 'Fleurs',
                'nature' => 'Personnes observant la nature',
                'forest' => 'Forêt',
                'architecture' => 'Architecture moderne',
                'mountains' => 'Paysage de montagne',
                'workshop' => 'Personnes réunies autour d’un projet',
            ][$photoName];
        }
        foreach ($previewPage['blocks'] as &$block) {
            if (($block['type'] ?? null) === 'gallery' && ($block['media_ids'] ?? null) === []) {
                $block['media_ids'] = array_keys($previewPhotos);
            }
        }
        unset($block);
    }
    $previewSite=InstallThemePreview::site($mode,$id);
    $previewSite['preview_theme']=$id;
    $previewSite['preview_kit']=$presentation['design_kit']['id'];
    $previewSite['footer_links']=[];
    foreach ([
        'mentions-legales'=>'Mentions légales',
        'confidentialite'=>'Confidentialité',
        'cookies'=>'Cookies et traceurs',
    ] as $slug=>$label) {
        $previewSite['footer_links'][]=[
            'label'=>$label,
            'url'=>'/theme-preview.php?theme='.rawurlencode($id)
                .'&mode=empty&page='.rawurlencode($slug),
        ];
    }
    $html=(new PublicPageRenderer())->render(
        $previewSite,
        $previewPage,
        $presentation,
        $presentation['css_url'],
        array_merge(InstallThemePreview::media($mode), $previewPhotos),
        InstallThemePreview::navigation($mode,$id),
        mediaAlts: array_merge(InstallThemePreview::alternatives(), $previewAlts)
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

