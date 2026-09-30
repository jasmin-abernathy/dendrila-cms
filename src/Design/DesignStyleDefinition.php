<?php
declare(strict_types=1);

namespace LePotager\Cms\Design;

final readonly class DesignStyleDefinition
{
    private const ENUMS = [
        'layout.content_width' => ['narrow', 'medium', 'wide', 'full'],
        'layout.section_spacing' => ['compact', 'balanced', 'airy'],
        'layout.hero' => ['minimal', 'text_first', 'media_first', 'split'],
        'layout.text_column' => ['narrow', 'medium', 'wide'],
        'layout.alignment' => ['left', 'center', 'mixed'],
        'typography.heading_style' => ['neutral', 'humanist', 'editorial', 'display'],
        'typography.body_style' => ['neutral', 'humanist', 'editorial'],
        'typography.scale' => ['restrained', 'balanced', 'expressive'],
        'media.prominence' => ['low', 'medium', 'high'],
        'media.crop' => ['natural', 'cover', 'mixed'],
        'media.gallery' => ['grid', 'masonry', 'filmstrip', 'editorial'],
        'illustration.frequency' => ['none', 'occasional', 'regular'],
        'illustration.style' => ['none', 'line', 'flat', 'geometric', 'organic'],
        'surfaces.radius' => ['none', 'small', 'medium', 'large'],
        'surfaces.borders' => ['none', 'subtle', 'present'],
        'surfaces.shadows' => ['none', 'subtle'],
        'accent.usage' => ['restrained', 'balanced', 'strong'],
        'accent.backgrounds' => ['neutral', 'soft_sections', 'alternating'],
        'motion.level' => ['none', 'subtle', 'expressive'],
    ];

    /** @param array<string, mixed> $definition */
    public function __construct(private array $definition)
    {
        if (($definition['schema_version'] ?? null) !== 1
            || !is_string($definition['id'] ?? null)
            || preg_match('/^[a-z][a-z0-9-]*$/', $definition['id']) !== 1
            || !is_string($definition['label'] ?? null)
            || trim($definition['label']) === ''
            || !is_string($definition['description'] ?? null)
            || trim($definition['description']) === ''
            || !is_array($definition['tokens'] ?? null)
            || !is_array($definition['constraints'] ?? null)) {
            throw new \InvalidArgumentException('Invalid design style definition.');
        }

        foreach (self::ENUMS as $path => $allowed) {
            $value = $this->token($path);
            if (!is_string($value) || !in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException("Invalid design token '{$path}'.");
            }
        }

        if (!is_bool($this->token('media.full_bleed'))) {
            throw new \InvalidArgumentException("Invalid design token 'media.full_bleed'.");
        }

        $constraints = $definition['constraints'];
        if (!in_array($constraints['contrast_target'] ?? null, ['AA', 'AAA'], true)
            || ($constraints['focus_visible'] ?? null) !== true
            || ($constraints['reduced_motion'] ?? null) !== true
            || ($constraints['autoplay_media'] ?? null) !== false) {
            throw new \InvalidArgumentException('Design style accessibility constraints are invalid.');
        }
    }

    public function id(): string { return $this->definition['id']; }
    public function label(): string { return $this->definition['label']; }

    public function token(string $path): mixed
    {
        $value = $this->definition['tokens'];
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new \InvalidArgumentException("Missing design token '{$path}'.");
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
