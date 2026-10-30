<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;
use Dendrila\Cms\Design\{DesignKitCatalog,PresentationResolver,SiteCompositionDefinition};
final class Presentation
{
    /** Resolve the same bundled theme for installation previews, drafts and public pages. */
    public static function forTheme(string $id, ?array $brand = null): array
    {
        $root = dirname(__DIR__, 2);
        if ($brand === null) {
            $raw = getenv('DENDRILA_BRAND_JSON') ?: '{}';
            $brand = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($brand)) throw new \InvalidArgumentException('Brand must be an object.');
        }
        $composition = new SiteCompositionDefinition([
            'schema_version'=>1, 'functional_kit'=>'native', 'design_style'=>$id,
            'brand_overrides'=>$brand,
        ]);
        if (!is_file($root.'/design-styles/'.$id.'/style.json')) throw new \InvalidArgumentException('Unknown theme.');
        $presentation = (new PresentationResolver(
            $root.'/design-styles', new DesignKitCatalog($root.'/public/design-kits/catalog.json')
        ))->resolve($composition);
        $theme = $presentation['design_kit'];
        $presentation['css_url'] = '/design-kits/themes/'.$theme['id'].'/'.$theme['entrypoint']
            .'?v='.substr(hash_file('sha256', $theme['entrypoint_path']), 0, 16);
        $presentation['support_css_url'] = '/assets/theme-runtime.css';
        return $presentation;
    }
    public static function consentFor(array $page):string {
        $providers=[];
        foreach($page['blocks']??[] as $block)if(($block['type']??'')==='embed_safe'){
            $id=$block['provider']??'';
            $label=['youtube-nocookie'=>'YouTube','vimeo'=>'Vimeo','openstreetmap'=>'OpenStreetMap'][$id]??null;
            if($label!==null)$providers[$id]=['id'=>$id,'label'=>$label,'category'=>'external_media'];
        }
        if($providers===[])return '';
        ksort($providers);
        return (new \Dendrila\Cms\Privacy\ConsentRenderer())->render([
            'enabled'=>true,'layout'=>'card','retention_days'=>180,'footer_link'=>true,
            'fingerprint'=>hash('sha256',json_encode($providers,JSON_THROW_ON_ERROR)),
            'services'=>array_values($providers),
        ]);
    }
}
