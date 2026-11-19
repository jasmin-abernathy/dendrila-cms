<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use InvalidArgumentException;

/**
 * Trusted renderer for the optional Oldweb-kit Ripple backdrop.
 *
 * Presentation contract (separate from design-style schema v1):
 * ['type' => 'ripple', 'media_id' => '...', 'speed' => 1.0, 'intensity' => 0.5]
 * All executable code belongs to the CMS, never to imported style packages.
 */
final class OldwebRippleBackdrop
{
    /**
     * @param array<string,mixed> $settings
     * @param array<string,string> $mediaUrls
     * @return array{head:string,markup:string,tail:string}
     */
    public function render(array $settings, array $mediaUrls, string $language): array
    {
        [$url, $speed, $intensity] = $this->validate($settings, $mediaUrls);
        $english = $language === 'en';
        $pause = $english ? 'Pause animated background' : 'Mettre le fond en pause';
        $resume = $english ? 'Resume animated background' : 'Reprendre le fond animé';

        $markup = '<div class="d-ripple-backdrop" data-dendrila-ripple data-ready="false" aria-hidden="true"'
            . ' data-speed="' . $speed . '" data-intensity="' . $intensity . '">'
            . '<img class="d-ripple-poster" alt="" src="' . self::escape($url) . '" decoding="async">'
            . '<canvas class="d-ripple-canvas" aria-hidden="true"></canvas></div>';

        $tail = '<button type="button" class="d-ripple-toggle" data-dendrila-ripple-toggle hidden'
            . ' data-pause-label="' . self::escape($pause) . '" data-resume-label="' . self::escape($resume) . '"'
            . '>' . self::escape($pause) . '</button>'
            . '<script defer src="/assets/oldweb-ripple.js"></script>';

        return [
            'head' => '<link rel="stylesheet" href="/assets/oldweb-ripple.css">',
            'markup' => $markup,
            'tail' => $tail,
        ];
    }

    /** @return array{string,string,string} */
    public function validate(array $settings, array $mediaUrls): array
    {
        if (array_diff(array_keys($settings), ['type', 'media_id', 'speed', 'intensity']) !== []
            || ($settings['type'] ?? null) !== 'ripple') {
            throw new InvalidArgumentException('Unsupported Oldweb backdrop configuration.');
        }

        $id = $settings['media_id'] ?? null;
        if (!is_string($id) || preg_match('/^[A-Za-z0-9_-]{1,128}$/', $id) !== 1
            || !array_key_exists($id, $mediaUrls)) {
            throw new InvalidArgumentException('Ripple requires an existing media id.');
        }

        $url = $mediaUrls[$id];
        if (!is_string($url) || preg_match('#^/(?!/)[A-Za-z0-9/_\.%-]+\.(?:png|jpe?g|webp|avif|svg)$#i', $url) !== 1
            || str_contains($url, '..') || str_contains($url, '%')) {
            throw new InvalidArgumentException('Ripple media must be a local public image.');
        }

        $speed = $this->range($settings['speed'] ?? 1, 0.25, 2.0, 'speed');
        $intensity = $this->range($settings['intensity'] ?? 0.5, 0.0, 1.0, 'intensity');
        return [$url, $speed, $intensity];
    }

    private function range(mixed $value, float $min, float $max, string $name): string
    {
        if ((!is_int($value) && !is_float($value))
            || !is_finite((float) $value) || $value < $min || $value > $max) {
            throw new InvalidArgumentException("Ripple {$name} must be within allowed bounds.");
        }
        return number_format((float) $value, 2, '.', '');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
