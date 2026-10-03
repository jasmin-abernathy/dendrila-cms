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
        return $variables;
    }

    /** @param array<string, string> $brand @return array<string, string> */
    public function assets(array $brand): array
    {
        $assets = [];
        foreach (['logo_url', 'wordmark_url'] as $key) {
            if (isset($brand[$key])) {
                $assets[$key] = $brand[$key];
            }
        }
        return $assets;
    }
}
