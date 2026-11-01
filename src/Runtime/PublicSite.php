<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;
use PDO;
use Dendrila\Cms\Front\{PublicPageRenderer,PdoPublicNavigationSource,PublicPrivacyDocumentRouter,PrivacyServicePublicDocumentSource};
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Storage\PdoCmsStore;
use Dendrila\Cms\Privacy\{PdoPrivacyRepository,PrivacyService};
use Dendrila\Cms\Media\MediaFactory;
final readonly class PublicSite
{
    public function __construct(private PDO $pdo){}
    public function render(string $path): ?string {
        $slug=trim($path,'/');if($slug==='')$slug='home';
        if(!preg_match('/^[a-z0-9][a-z0-9-]{0,189}$/D',$slug))return null;
        $siteId=Application::site();
        $site=['name'=>getenv('DENDRILA_SITE_NAME')?:'Dendrila','home_url'=>'/'];
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
        $q=$this->pdo->prepare("SELECT published_values_json FROM contents WHERE site_id=? AND type_id='page' AND published_slug=? AND published_values_json IS NOT NULL AND published_revision_id IS NOT NULL AND status<>'archived'");
        $q->execute([$siteId,$slug]);
        $json=$q->fetchColumn();
        if(!is_string($json))return null;
        $page=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        $media=MediaFactory::fromEnvironment($this->pdo);
        return (new PublicPageRenderer())->render(
            $site,$page,$presentation,$presentation['css_url'],
            $media?->publicMap($siteId)??[],$navigation,Presentation::consentFor($page),
            mediaAlts:$media?->publicAltMap($siteId)??[]
        );

    }
}
