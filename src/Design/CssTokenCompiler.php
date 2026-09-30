<?php
declare(strict_types=1);

namespace Dendrila\Cms\Design;

final class CssTokenCompiler
{
    /** @return array<string, string> */
    public function variables(DesignStyleDefinition $style): array
    {
        return [
            '--dendrila-content-max' => $this->map($style, 'layout.content_width', ['narrow'=>'48rem','medium'=>'68rem','wide'=>'84rem','full'=>'100%']),
            '--dendrila-section-gap' => $this->map($style, 'layout.section_spacing', ['compact'=>'2.5rem','balanced'=>'4rem','airy'=>'6rem']),
            '--dendrila-text-max' => $this->map($style, 'layout.text_column', ['narrow'=>'42rem','medium'=>'54rem','wide'=>'68rem']),
            '--dendrila-radius' => $this->map($style, 'surfaces.radius', ['none'=>'0','small'=>'0.35rem','medium'=>'0.8rem','large'=>'1.4rem']),
            '--dendrila-border-width' => $this->map($style, 'surfaces.borders', ['none'=>'0','subtle'=>'1px','present'=>'2px']),
            '--dendrila-shadow' => $this->map($style, 'surfaces.shadows', ['none'=>'none','subtle'=>'0 0.5rem 1.5rem rgb(0 0 0 / 0.08)']),
            '--dendrila-heading-scale' => $this->map($style, 'typography.scale', ['restrained'=>'1','balanced'=>'1.12','expressive'=>'1.28']),
            '--dendrila-media-emphasis' => $this->map($style, 'media.prominence', ['low'=>'0.75','medium'=>'1','high'=>'1.2']),
            '--dendrila-accent-strength' => $this->map($style, 'accent.usage', ['restrained'=>'0.35','balanced'=>'0.65','strong'=>'1']),
            '--dendrila-motion-duration' => $this->map($style, 'motion.level', ['none'=>'0ms','subtle'=>'160ms','expressive'=>'260ms']),
        ];
    }

    public function compile(DesignStyleDefinition $style, string $selector = ':root'): string
    {
        if (preg_match('/^[#.:[\]a-zA-Z0-9_="\- ]{1,160}$/', $selector) !== 1) {
            throw new \InvalidArgumentException('Unsafe CSS selector.');
        }
        $lines = [$selector . ' {'];
        foreach ($this->variables($style) as $name => $value) $lines[] = '  ' . $name . ': ' . $value . ';';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '@media (prefers-reduced-motion: reduce) {';
        $lines[] = '  ' . $selector . ' { --dendrila-motion-duration: 0ms; }';
        $lines[] = '}';
        return implode("\n", $lines) . "\n";
    }

    /** @param array<string, string> $mapping */
    private function map(DesignStyleDefinition $style, string $path, array $mapping): string
    {
        $value = $style->token($path);
        if (!is_string($value) || !isset($mapping[$value])) throw new \InvalidArgumentException("Unsupported design token '{$path}'.");
        return $mapping[$value];
    }
}
