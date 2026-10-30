<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;
use PDO;
use Dendrila\Cms\Front\{PublicPageRenderer,PdoPublicNavigationSource};
use Dendrila\Cms\Media\MediaFactory;
final readonly class PublicSite
{
    public function __construct(private PDO $pdo){}
    public function render(string $path): ?string {
        $slug=trim($path,'/');if($slug==='')$slug='home';
        if(!preg_match('/^[a-z0-9][a-z0-9-]{0,189}$/D',$slug))return null;
        $q=$this->pdo->prepare("SELECT published_values_json FROM contents WHERE site_id=? AND type_id='page' AND published_slug=? AND published_values_json IS NOT NULL AND published_revision_id IS NOT NULL AND status<>'archived'");
        $q->execute([Application::site(),$slug]);$json=$q->fetchColumn();if(!is_string($json))return null;
        $page=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        return (new PublicPageRenderer())->render(['name'=>getenv('DENDRILA_SITE_NAME')?:'Dendrila','home_url'=>'/'], $page,Presentation::forTheme(getenv('DENDRILA_THEME')?:'quiet-elegant'),'/assets/site.css',MediaFactory::fromEnvironment($this->pdo)?->publicMap(Application::site())??[],(new PdoPublicNavigationSource($this->pdo))->items(Application::site()),Presentation::consentFor($page));
    }
}
