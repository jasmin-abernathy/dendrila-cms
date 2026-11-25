<?php
declare(strict_types=1);

namespace Dendrila\Cms\Design;

use Dendrila\Cms\Front\OldwebGifMosaic;
use Dendrila\Cms\Front\OldwebRippleBackdrop;
use InvalidArgumentException;

final readonly class OldwebAppearance
{
    public const THEMES = [
        'oldweb-personal' => 'Page personnelle',
        'oldweb-portal' => 'Portail à encadrés',
        'oldweb-blog' => 'Blog illustré',
    ];

    public const PALETTES = [
        'default' => ['Thème', null],
        'plum' => ['Prune', '#603879'],
        'blue' => ['Bleu', '#16466C'],
        'rust' => ['Terre cuite', '#743516'],
    ];

    public function __construct(private PresentationResolver $resolver) {}

    /**
     * Apply editorial choices to the host site's composition and its public media map.
     * Validation is shared by preview and publication.
     */
    public function resolve(array $composition, array $settings, array $publicMedia, array $gifMetadata = []): array
    {
        $style = $settings['style'] ?? null;
        $palette = $settings['palette'] ?? 'default';
        $mediaId = $settings['media_id'] ?? '';
        if (!is_string($style) || !isset(self::THEMES[$style])
            || !is_string($palette) || !isset(self::PALETTES[$palette])
            || !is_string($mediaId)) {
            throw new InvalidArgumentException('Thème, palette ou image de fond invalide.');
        }

        $composition['design_style'] = $style;
        $color = self::PALETTES[$palette][1];
        if ($color !== null) {
            $composition['brand_overrides']['primary'] = $color;
        }
        $presentation = $this->resolver->resolve(new SiteCompositionDefinition($composition));

        if (array_key_exists('gif_ids', $settings) && $settings['gif_ids'] !== []) {
            $presentation['gif_mosaic'] = OldwebGifMosaic::resolve($settings['gif_ids'], $publicMedia, $gifMetadata);
        } elseif ($mediaId !== '') {
            $backdrop = [
                'type' => 'ripple',
                'media_id' => $mediaId,
                'speed' => $settings['speed'] ?? 1,
                'intensity' => $settings['intensity'] ?? 0.5,
            ];
            (new OldwebRippleBackdrop())->validate($backdrop, $publicMedia);
            $presentation['backdrop'] = $backdrop;
        }

        return $presentation;
    }
}
