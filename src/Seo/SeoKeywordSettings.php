<?php
declare(strict_types=1);
namespace Dendrila\Cms\Seo;

use PDO;

/**
 * SEO writing topics stored in the existing site-scoped settings JSON.
 * No new schema or external service; appearance updates preserve seo_* keys.
 */
final class SeoKeywordSettings
{
    public static function normalize(string|array $input, int $limit=25): array
    {
        $words=is_string($input)?preg_split('/[,;\r\n]+/u',$input):$input;
        if(!is_array($words)||count($words)>$limit*3)throw new \InvalidArgumentException('Liste de mots-clés trop longue.');
        $out=[];$seen=[];
        foreach($words as $word){
            if(!is_string($word))throw new \InvalidArgumentException('Mot-clé invalide.');
            $word=trim(preg_replace('/\s+/u',' ',$word) ?? '');
            if($word==='')continue;
            if(mb_strlen($word)>80 || preg_match('/[<>"\x00-\x1f]/u',$word))
                throw new \InvalidArgumentException('Un mot-clé dépasse 80 caractères ou comporte un caractère interdit.');
            $fold=mb_strtolower($word,'UTF-8');
            if(isset($seen[$fold]))continue;
            $seen[$fold]=true;$out[]=$word;
            if(count($out)>$limit)throw new \InvalidArgumentException('Maximum '.$limit.' mots-clés.');
        }
        return $out;
    }

    public static function read(PDO $pdo,string $siteId): array
    {
        $q=$pdo->prepare('SELECT branding_json FROM site_api_settings WHERE site_id=?');
        $q->execute([$siteId]);$raw=$q->fetchColumn();
        if(!is_string($raw))throw new \RuntimeException('Réglages du site introuvables.');
        $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($data))throw new \RuntimeException('Réglages SEO illisibles.');
        $keywords=self::normalize($data['seo_keywords']??[],25);
        $pageLists=$data['seo_page_keywords']??[];
        if(!is_array($pageLists)||count($pageLists)>500)throw new \RuntimeException('Réglages SEO par page invalides.');
        $pages=[];
        foreach($pageLists as $id=>$words){
            if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$id))
                throw new \RuntimeException('Identifiant de page SEO invalide.');
            $pages[$id]=self::normalize($words,5);
        }
        return ['raw'=>$raw,'keywords'=>$keywords,'pages'=>$pages];
    }

    public static function save(PDO $pdo,string $siteId,string $expectedHash,array $keywords,array $pages):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$expectedHash))
            throw new \InvalidArgumentException('Version de formulaire invalide.');
        $keywords=self::normalize($keywords);
        if(count($pages)>500)throw new \InvalidArgumentException('Trop de pages SEO.');
        $norm=[];
        foreach($pages as $id=>$terms){
            if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$id))
                throw new \InvalidArgumentException('Page SEO invalide.');
            $value=self::normalize($terms,5);
            // A targeted topic may be authored independently of the global bank.
            if($value!==[])$norm[$id]=$value;
        }
        $sqlite=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $ownTransaction=!$pdo->inTransaction();
        if($ownTransaction){if($sqlite)$pdo->exec('BEGIN IMMEDIATE');else $pdo->beginTransaction();}
        try{
            $q=$pdo->prepare('SELECT branding_json FROM site_api_settings WHERE site_id=?'.($sqlite?'':' FOR UPDATE'));
            $q->execute([$siteId]);$raw=$q->fetchColumn();
            if(!is_string($raw)||!hash_equals(hash('sha256',$raw),$expectedHash))
                throw new \DomainException('Les réglages ont été modifiés ailleurs. Rechargez la page.');
            $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($data))throw new \RuntimeException('Réglages illisibles.');
            $data['seo_keywords']=$keywords;
            $data['seo_page_keywords']=(object)$norm;
            $json=json_encode((object)$data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
            if($json!==$raw){
                $q=$pdo->prepare('UPDATE site_api_settings SET branding_json=?, updated_at='
                    .($sqlite?"strftime('%Y-%m-%d %H:%M:%f','now')":'UTC_TIMESTAMP(6)').' WHERE site_id=?');
                $q->execute([$json,$siteId]);
                $q=$pdo->prepare('UPDATE sites SET config_version=config_version+1 WHERE site_id=?');
                $q->execute([$siteId]);
            }
            if($ownTransaction)$pdo->commit();
        }catch(\Throwable $e){if($ownTransaction&&$pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    /** Explicit per-page selection wins; otherwise only relevant site topics are emitted. */
    public static function keywordsForPage(array $settings,string $contentId,array $page):array
    {
        $selected=$settings['pages'][$contentId]??[];
        if($selected!==[])return self::normalize($selected,5);
        $visible=SeoWritingAdvisor::pageText($page);
        $matches=[];
        foreach($settings['keywords']??[] as $term){
            if(SeoWritingAdvisor::contains($visible,$term))$matches[]=$term;
            if(count($matches)>=5)break;
        }
        return $matches;
    }
}
