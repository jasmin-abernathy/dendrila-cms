<?php
declare(strict_types=1);

namespace Dendrila\Cms\Design;

final class BrandTokenCompiler
{
    private const COLOR_MAP = [
        'primary' => '--dendrila-color-primary',
        'secondary' => '--dendrila-color-secondary',
        'primary_strong' => '--dendrila-color-primary-strong',
        'background' => '--dendrila-color-bg',
        'surface' => '--dendrila-color-surface',
        'text' => '--dendrila-color-text',
        'muted' => '--dendrila-color-muted',
        'accent_soft' => '--dendrila-color-accent-soft',
        'border' => '--dendrila-color-border',
    ];

    private const FONT_MAP = [
        'neutral-sans' => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'humanist-sans' => 'Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
        'editorial-serif' => 'Georgia, "Times New Roman", serif',
        'system-serif' => 'ui-serif, Georgia, "Times New Roman", serif',
    ];

    /** @param array<string, string> $brand @return array<string, string> */
    public function variables(array $brand): array
    {
        $variables = [];
        foreach (self::COLOR_MAP as $key => $variable) {
            if (isset($brand[$key])) {
                $variables[$variable] = $brand[$key];
            }
        }
        if (isset($brand['heading_font'])) {
            $variables['--dendrila-font-heading'] = self::FONT_MAP[$brand['heading_font']];
        }
        if (isset($brand['body_font'])) {
            $variables['--dendrila-font-body'] = self::FONT_MAP[$brand['body_font']];
        }
        foreach (['heading_font_file'=>'heading','body_font_file'=>'body'] as $key=>$target) {
            if(isset($brand[$key])) {
                $family=$target==='heading'?'DendrilaImportedHeading':'DendrilaImportedBody';
                $variables['--dendrila-font-'.$target] = '"'.$family.'", system-ui, sans-serif';
            }
        }
        return $variables;
    }

    /** First-party CSS generated only from validated font tokens. */
    public function fontFaces(array $brand): string
    {
        $rules=[];
        $formats=['woff2'=>'woff2','woff'=>'woff','truetype'=>'truetype','opentype'=>'opentype'];
        foreach(['heading_font_file'=>'DendrilaImportedHeading','body_font_file'=>'DendrilaImportedBody'] as $key=>$family) {
            if(!isset($brand[$key]))continue;
            $ref=$brand[$key];
            if(!is_string($ref)||!preg_match('/^(woff2|woff|truetype|opentype):[a-f0-9]{48}$/D',$ref,$match))
                throw new \InvalidArgumentException('Invalid uploaded font reference.');
            $rules[]='@font-face{font-family:"'.$family.'";src:url("/brand-font.php?font='
                .rawurlencode($ref).'") format("'.$formats[$match[1]].'");font-display:swap;}';
        }
        return implode("\n",$rules);
    }

    /** @param array<string, string> $brand @return array<string, string> */
    public function assets(array $brand): array
    {
        $assets = [];
        foreach (['logo_url', 'wordmark_url', 'hero_image_url'] as $key) {
            if (isset($brand[$key])) {
                $assets[$key] = $brand[$key];
            }
        }
        return $assets;
    }
}
