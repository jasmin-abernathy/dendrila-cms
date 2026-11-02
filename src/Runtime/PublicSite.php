<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;
use PDO;
use Dendrila\Cms\Front\{PublicPageRenderer,PdoPublicNavigationSource,PublicPrivacyDocumentRouter,PrivacyServicePublicDocumentSource};
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Privacy\{PdoPrivacyRepository,PrivacyService};
use Dendrila\Cms\Media\MediaFactory;
use Dendrila\Cms\Front\PdoPublicSeoSource;
use Dendrila\Cms\Seo\SeoKeywordSettings;
final readonly class PublicSite
{
    public function __construct(private PDO $pdo){}
    public function render(string $path): ?string {
        $slug=trim($path,'/');if($slug==='')$slug='home';
        if(!preg_match('/^[a-z0-9][a-z0-9-]{0,189}$/D',$slug))return null;
        $siteId=Application::site();
        $site=['name'=>getenv('DENDRILA_SITE_NAME')?:'Dendrila','home_url'=>'/'];
        $base=getenv('APP_BASE_URL')?:'';
        if(filter_var($base,FILTER_VALIDATE_URL)
           && strtolower((string)parse_url($base,PHP_URL_SCHEME))==='https')
            $site['canonical_base_url']=rtrim($base,'/');
        $privacy=new PrivacyService(
            new PdoPrivacyRepository($this->pdo),
            new AuthorizationService(new PdoCmsStore($this->pdo))
        );
        // The privacy system only exposes approved, non-stale legal documents.
        // No example or unapproved document should become a live footer link.
        $titles=[
            '/mentions-legales'=>'Mentions légales',
            '/confidentialite'=>'Politique de confidentialité',
            '/cookies'=>'Cookies et traceurs',
        ];
        $legalLinks=[];
        foreach(PublicPrivacyDocumentRouter::ROUTES as $url=>$kind) {
            if($privacy->publicLegalDocument($siteId,$kind)===null) continue;
            $legalLinks[]=['url'=>$url,'label'=>$titles[$url]];
        }
        $site['footer_links']=$legalLinks;
        $navigation=(new PdoPublicNavigationSource($this->pdo))->items($siteId);
        $presentation=Presentation::forSite($this->pdo);
        if(isset(PublicPrivacyDocumentRouter::ROUTES['/'.$slug])) {
            return (new PublicPrivacyDocumentRouter(
                new PrivacyServicePublicDocumentSource($privacy)
            ))->render($siteId,'/'.$slug,$site,$presentation,
                $presentation['css_url'],$navigation);
        }
        $q=$this->pdo->prepare("SELECT content_id,published_values_json FROM contents WHERE site_id=? AND type_id='page' AND published_slug=? AND published_values_json IS NOT NULL AND published_revision_id IS NOT NULL AND status<>'archived'");
        $q->execute([$siteId,$slug]);
        $record=$q->fetch(PDO::FETCH_ASSOC);
        if(!is_array($record) || !is_string($record['published_values_json']??null))return null;
        $page=json_decode($record['published_values_json'],true,512,JSON_THROW_ON_ERROR);
        // Only the published revision is used for public SEO recommendations.
        $seo=(new PdoPublicSeoSource($this->pdo))->metadata($siteId,(string)$record['content_id']);
        $topics=SeoKeywordSettings::read($this->pdo,$siteId);
        $seo['keywords']=SeoKeywordSettings::keywordsForPage($topics,(string)$record['content_id'],$page);
        $media=MediaFactory::fromEnvironment($this->pdo);
        if($slug==='contact' && is_array($page['blocks']??null)){
            $hasForm=count(array_filter($page['blocks'],
                static fn(mixed $block):bool=>is_array($block) && ($block['type']??null)==='contact_form'))>0;
            if($hasForm)$site['contact_form']=\Dendrila\Cms\Front\PublicContactForm::context();
        }
        return (new PublicPageRenderer())->render(
            $site,$page,$presentation,$presentation['css_url'],
            $media?->publicMap($siteId)??[],$navigation,Presentation::consentFor($page),
            seo:$seo,mediaAlts:$media?->publicAltMap($siteId)??[]
        );

    }
}
