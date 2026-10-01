<?php
declare(strict_types=1);

namespace LePotager\Cms\Design;

/** Closed token vocabulary; the renderer owns the markup and stylesheet. */
final class FrontStyleAdapter
{
    /** @return array<string, string> */
    public function attributes(DesignStyleDefinition $style): array
    {
        $attributes = ['data-design-style' => $style->id()];
        foreach ([
            'hero'=>'layout.hero', 'alignment'=>'layout.alignment',
            'crop'=>'media.crop', 'gallery'=>'media.gallery',
            'heading'=>'typography.heading_style', 'body'=>'typography.body_style',
            'illustration-frequency'=>'illustration.frequency',
            'illustration'=>'illustration.style', 'backgrounds'=>'accent.backgrounds',
        ] as $name => $path) {
            $attributes['data-' . $name] = $style->token($path);
        }
        $attributes['data-full-bleed'] = $style->token('media.full_bleed') ? 'true' : 'false';
        return $attributes;
    }

    public function htmlAttributes(DesignStyleDefinition $style): string
    {
        $parts = [];
        foreach ($this->attributes($style) as $name => $value) {
            $parts[] = $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }
        return implode(' ', $parts);
    }
}
