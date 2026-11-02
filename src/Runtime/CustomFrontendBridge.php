<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;

use Dendrila\Cms\Front\{PdoPublicNavigationSource,PdoPublicSeoSource};
use Dendrila\Cms\Media\MediaFactory;
use Dendrila\Cms\Seo\{SeoHeadRenderer,SeoKeywordSettings};
use PDO;

/**
 * Native, read-only bridge for sites whose PHP templates own their entire design.
 *
 * This deliberately does not call Presentation/DesignKitCatalog/PublicPageRenderer:
 * the developer decides the HTML, CSS, components and routing. The back-office
 * keeps ownership of authenticated editorial workflows and publication.
 */
final readonly class CustomFrontendBridge
{
    public function __construct(private PDO $pdo,private string $siteId)
    {
        if(!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D',$siteId))
            throw new \InvalidArgumentException('Identifiant de site invalide.');
    }

    /** Only publicly published, non-archived CMS pages; never unpublished drafts. */
    public function page(string $slug):?array
    {
        $slug=trim($slug,'/');
        if($slug==='')$slug='home';
        if(!preg_match('/^[a-z0-9][a-z0-9-]{0,189}$/D',$slug))
            throw new \InvalidArgumentException('Adresse de page invalide.');
        $query=$this->pdo->prepare(
            "SELECT content_id,published_values_json,published_slug FROM contents
             WHERE site_id=? AND type_id='page' AND published_slug=?
             AND published_values_json IS NOT NULL AND published_revision_id IS NOT NULL
             AND status<>'archived' LIMIT 1"
        );
        $query->execute([$this->siteId,$slug]);
        $record=$query->fetch(PDO::FETCH_ASSOC);
        if(!is_array($record))return null;
        $values=json_decode((string)$record['published_values_json'],true,512,JSON_THROW_ON_ERROR);
        if(!is_array($values)||!is_string($values['title']??null)
           ||!is_array($values['blocks']??null)||!array_is_list($values['blocks']))
            throw new \RuntimeException('Page publiée invalide.');
        return [
            'id'=>(string)$record['content_id'],
            'slug'=>$slug,
            'title'=>$values['title'],
            'blocks'=>$values['blocks'],
        ];
    }

    /** Existing public navigation, respecting hidden entries. */
    public function navigation(string $location='primary'):array
    {
        return (new PdoPublicNavigationSource($this->pdo))->items($this->siteId,$location);
    }

    /** Return verified media IDs/URLs from the native library when enabled. */
    public function media():array
    {
        return MediaFactory::fromEnvironment($this->pdo)?->publicMap($this->siteId)??[];
    }

    /**
     * Each component stays structured: the template chooses its markup.
     * Duplicate headings can be disambiguated by occurrence index.
     */
    public function component(array $page,string $type,?string $heading=null,int $occurrence=0):?array
    {
        if(!preg_match('/^[a-z][a-z_]{0,39}$/D',$type)||$occurrence<0||$occurrence>199)
            throw new \InvalidArgumentException('Sélecteur de composant invalide.');
        $matches=0;
        foreach($page['blocks']??[] as $block){
            if(!is_array($block)||($block['type']??null)!==$type)continue;
            if($heading!==null&&($block['heading']??null)!==$heading)continue;
            if($matches++===$occurrence)return $block;
        }
        return null;
    }

    /** Escaped text for custom HTML templates; content must never be echoed raw. */
    public static function escape(mixed $value):string
    {
        if(!is_scalar($value)&&$value!==null)
            throw new \InvalidArgumentException('Valeur non textuelle.');
        return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    }

    /** Escape and validate links in developer-owned markup. */
    public static function href(string $url):string
    {
        $ok=(str_starts_with($url,'/')&&!str_starts_with($url,'//'))
            || (filter_var($url,FILTER_VALIDATE_URL)!==false
                && parse_url($url,PHP_URL_SCHEME)==='https'
                && parse_url($url,PHP_URL_USER)===null
                && parse_url($url,PHP_URL_PASS)===null);
        if(!$ok||preg_match('/[\x00-\x20]/',$url))
            throw new \InvalidArgumentException('Lien non autorisé.');
        return self::escape($url);
    }

    /**
     * Native metadata and site keywords without any forced visual kit.
     * Canonical origin is provided by the consuming site, never guessed.
     */
    public function seoHead(array $page,string $siteName,string $httpsOrigin):string
    {
        $parsed=parse_url($httpsOrigin);
        if(!is_array($parsed)||($parsed['scheme']??null)!=='https'
            ||!isset($parsed['host'])||isset($parsed['user'],$parsed['pass'])
            ||isset($parsed['query'])||isset($parsed['fragment'])
            ||(isset($parsed['path'])&&$parsed['path']!==''&&$parsed['path']!=='/'))
            throw new \InvalidArgumentException('Origine HTTPS invalide.');
        if(!is_string($page['id']??null)||!is_string($page['slug']??null)
            ||!is_string($page['title']??null))throw new \InvalidArgumentException('Page invalide.');
        $metadata=(new PdoPublicSeoSource($this->pdo))->metadata($this->siteId,$page['id']);
        $keywords=SeoKeywordSettings::read($this->pdo,$this->siteId);
        $metadata['keywords']=SeoKeywordSettings::keywordsForPage($keywords,$page['id'],$page);
        if(($metadata['canonical_url']??null)===null)
            $metadata['canonical_url']=$page['slug']==='home'?'/':'/'.$page['slug'].'/';
        return (new SeoHeadRenderer())->render(
            ['name'=>$siteName,'canonical_base_url'=>rtrim($httpsOrigin,'/')],
            ['title'=>$page['title']],$metadata
        );
    }
}
