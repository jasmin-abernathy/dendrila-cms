<?php
declare(strict_types=1);
namespace Dendrila\Cms\Runtime;
use Dendrila\Cms\Installation\Catalog;
use Dendrila\Cms\Design\{DesignStyleDefinition,CssTokenCompiler};
final class Presentation
{
    public static function forTheme(string $id):array {
        $data=Catalog::themes()[$id]??throw new \InvalidArgumentException('Unknown theme.');$style=new DesignStyleDefinition($data);
        return ['design_kit'=>['id'=>$id],'compiled_css'=>(new CssTokenCompiler())->compile($style),
            'structure'=>['hero'=>$style->token('layout.hero'),'alignment'=>$style->token('layout.alignment'),'gallery'=>$style->token('media.gallery'),'media_crop'=>$style->token('media.crop'),'full_bleed'=>$style->token('media.full_bleed'),'accent_backgrounds'=>$style->token('accent.backgrounds'),'illustration_frequency'=>$style->token('illustration.frequency'),'illustration_style'=>$style->token('illustration.style'),'heading_style'=>$style->token('typography.heading_style'),'body_style'=>$style->token('typography.body_style')]];
    }
}
