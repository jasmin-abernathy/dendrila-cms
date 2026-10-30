<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;

use Dendrila\Cms\Design\SiteCompositionDefinition;
use Dendrila\Cms\Installation\Catalog;
use PDO;

/**
 * Site-scoped appearance; brand JSON is shared with MMW and remains an object.
 * The chosen theme and brand override are intentionally separate from site content.
 */
final class AppearanceSettings
{
    public const COLORS = [
        'primary'=>'Couleur principale', 'primary_strong'=>'Couleur principale foncée',
        'secondary'=>'Couleur secondaire', 'background'=>'Fond de page',
        'surface'=>'Fond des cartes', 'text'=>'Texte', 'muted'=>'Texte secondaire',
        'accent_soft'=>'Fond d’accent discret', 'border'=>'Bordures',
    ];
    public const FONTS = [
        'neutral-sans'=>'Sans serif neutre', 'humanist-sans'=>'Sans serif humaniste',
        'editorial-serif'=>'Serif éditoriale', 'system-serif'=>'Serif système',
    ];
    public const ASSETS = ['logo_url'=>'URL du logo', 'wordmark_url'=>'URL du logotype'];
    public const THEMES = [
        'simple-illustrated'=>'Simple avec quelques illustrations',
        'quiet-elegant'=>'Sobre et élégant', 'image-forward'=>'Centré sur les images',
        'ultra-minimal'=>'Très minimal', 'graphic-rhythm'=>'Dynamique et graphique',
        'editorial'=>'Éditorial',
    ];

    /** @return array{raw:string,theme:string,brand:array<string,string>} */
    public static function read(PDO $pdo, string $siteId): array
    {
        $q=$pdo->prepare('SELECT branding_json FROM site_api_settings WHERE site_id=?');
        $q->execute([$siteId]);
        $raw=$q->fetchColumn();
        if(!is_string($raw))throw new \RuntimeException('Site appearance not initialized.');
        $data=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
        if(!is_array($data)||($data!==[] && array_is_list($data)))throw new \RuntimeException('Invalid stored appearance.');
        $style=$data['design_style']??(getenv('DENDRILA_THEME')?:'quiet-elegant');
        if(!is_string($style)||!isset(self::THEMES[$style]))throw new \RuntimeException('Invalid stored design style.');
        $brand=array_intersect_key($data,array_flip(array_merge(array_keys(self::COLORS),array_keys(self::ASSETS),['heading_font','body_font'])));
        self::validate($style,$brand);
        return ['raw'=>$raw,'theme'=>$style,'brand'=>$brand];
    }

    /** Validate the complete set before touching the database or rendering CSS. */
    public static function validate(string $theme, array $brand): void
    {
        if(!isset(self::THEMES[$theme]) || !isset(Catalog::themes()[$theme]))throw new \InvalidArgumentException('Unknown design style.');
        new SiteCompositionDefinition([
            'schema_version'=>1,'functional_kit'=>'native',
            'design_style'=>$theme,'brand_overrides'=>$brand,
        ]);
        if(isset($brand['text'],$brand['background'])
            && self::contrast($brand['text'],$brand['background'])<4.5)
            throw new \InvalidArgumentException('Le contraste texte / fond doit atteindre WCAG AA (4,5:1).');
        if(isset($brand['text'],$brand['surface'])
            && self::contrast($brand['text'],$brand['surface'])<4.5)
            throw new \InvalidArgumentException('Le contraste texte / cartes doit atteindre WCAG AA (4,5:1).');
    }

    /** Optimistic lock on exact previous JSON; preserve unrelated safe MMW keys. */
    public static function save(PDO $pdo,string $siteId,string $expectedHash,string $theme,array $brand):void
    {
        self::validate($theme,$brand);
        if(!preg_match('/^[a-f0-9]{64}$/D',$expectedHash))throw new \InvalidArgumentException('Version de formulaire invalide.');
        $pdo->beginTransaction();
        try {
            $q=$pdo->prepare('SELECT branding_json FROM site_api_settings WHERE site_id=? FOR UPDATE');
            $q->execute([$siteId]);$raw=$q->fetchColumn();
            if(!is_string($raw)||!hash_equals(hash('sha256',$raw),$expectedHash))throw new \DomainException('Les réglages ont changé dans un autre onglet. Rechargez la page.');
            $previous=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
            if(!is_array($previous))throw new \RuntimeException('Stored appearance corrupted.');
            $allowed=array_flip(array_merge(array_keys(self::COLORS),array_keys(self::ASSETS),['heading_font','body_font','design_style']));
            $new=array_diff_key($previous,$allowed);
            $new['design_style']=$theme;
            foreach($brand as $k=>$v)$new[$k]=$v;
            $json=json_encode((object)$new,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            if($json!==$raw) {
                $q=$pdo->prepare('UPDATE site_api_settings SET branding_json=?,updated_at=UTC_TIMESTAMP(6) WHERE site_id=?');
                $q->execute([$json,$siteId]);
                $q=$pdo->prepare('UPDATE sites SET config_version=config_version+1 WHERE site_id=?');
                $q->execute([$siteId]);
            }
            $pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private static function contrast(string $a,string $b):float
    {
        $l=function(string $c):float {
            $parts=[hexdec(substr($c,1,2))/255,hexdec(substr($c,3,2))/255,hexdec(substr($c,5,2))/255];
            $p=array_map(static fn(float $n):float=>$n<=0.04045?$n/12.92:(($n+0.055)/1.055)**2.4,$parts);
            return 0.2126*$p[0]+0.7152*$p[1]+0.0722*$p[2];
        };
        $x=$l($a);$y=$l($b);return (max($x,$y)+0.05)/(min($x,$y)+0.05);
    }
}
