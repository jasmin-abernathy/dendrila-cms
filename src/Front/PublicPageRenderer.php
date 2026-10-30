<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use Dendrila\Cms\Privacy\ConsentResourceRenderer;
use Dendrila\Cms\Seo\SeoHeadRenderer;
use InvalidArgumentException;

final readonly class PublicPageRenderer
{
    private const HERO_LAYOUTS = ['minimal', 'text_first', 'media_first', 'split'];
    private const ALIGNMENTS = ['left', 'center', 'mixed'];
    private const GALLERIES = ['grid', 'masonry', 'filmstrip', 'editorial'];
    private const MEDIA_CROPS = ['natural', 'cover', 'mixed'];
    private const BACKGROUNDS = ['neutral', 'soft_sections', 'alternating'];
    private const ILLUSTRATION_FREQUENCIES = ['none', 'occasional', 'regular'];
    private const ILLUSTRATION_STYLES = ['none', 'line', 'flat', 'geometric', 'organic'];
    private const HEADING_STYLES = ['neutral', 'humanist', 'editorial', 'display'];
    private const BODY_STYLES = ['neutral', 'humanist', 'editorial'];

    public function __construct(
        private ConsentResourceRenderer $consentResources = new ConsentResourceRenderer(),
        private SeoHeadRenderer $seoHead = new SeoHeadRenderer(),
    ) {}

    public function render(
        array $site,
        array $page,
        array $presentation,
        string $themeCssUrl,
        array $mediaUrls = [],
        array $navigation = [],
        string $privacyMarkup = '',
        array $seo = [],
        array $mediaAlts = [],
    ): string {
        $siteName = self::shortText($site['name'] ?? null, 'site name', 190);
        $language = in_array($site['language'] ?? 'fr', ['fr', 'en'], true) ? (string) ($site['language'] ?? 'fr') : 'fr';
        $homeUrl = self::safeLinkUrl((string) ($site['home_url'] ?? '/'));
        $tagline = isset($site['tagline']) ? self::shortText($site['tagline'], 'site tagline', 300, true) : '';
        $title = self::shortText($page['title'] ?? null, 'page title', 300);
        $blocks = $page['blocks'] ?? null;
        if (!is_array($blocks) || !array_is_list($blocks) || count($blocks) > 200) {
            throw new InvalidArgumentException('Page blocks must be a bounded list.');
        }

        $theme = $presentation['design_kit'] ?? null;
        if (!is_array($theme) || !is_string($theme['id'] ?? null)
            || preg_match('/^[a-z][a-z0-9-]*$/', $theme['id']) !== 1) {
            throw new InvalidArgumentException('Invalid presentation design kit.');
        }
        $themeCssUrl = self::safeAssetUrl($themeCssUrl);
        $compiledCss = $presentation['compiled_css'] ?? '';
        if (!is_string($compiledCss) || strlen($compiledCss) > 100_000
            || str_contains(strtolower($compiledCss), '</style')) {
            throw new InvalidArgumentException('Invalid compiled presentation CSS.');
        }

        $structure = $this->structure($presentation);
        $supportedShapes = $presentation['media']['supported_shapes'] ?? ['auto', 'rounded', 'none'];
        if (!is_array($supportedShapes) || !array_is_list($supportedShapes)
            || count($supportedShapes) === 0 || count($supportedShapes) > 30
            || count(array_filter($supportedShapes, static fn (mixed $shape): bool =>
                is_string($shape) && preg_match('/^[a-z][a-z0-9-]*$/', $shape) === 1
            )) !== count($supportedShapes)) {
            throw new InvalidArgumentException('Invalid presentation media shapes.');
        }

        foreach ($mediaUrls as $mediaId => $url) {
            if (!is_string($mediaId) || preg_match('/^[A-Za-z0-9_-]{1,128}$/', $mediaId) !== 1 || !is_string($url)) {
                throw new InvalidArgumentException('Invalid media URL map.');
            }
            self::safeAssetUrl($url);
        }

        if (strlen($privacyMarkup) > 500_000
            || str_contains(strtolower($privacyMarkup), '<html')
            || str_contains(strtolower($privacyMarkup), '</body')) {
            throw new InvalidArgumentException('Invalid privacy frontend markup.');
        }

        [$hero, $bodyBlocks] = $this->extractHero($blocks, $structure['hero']);
        $bodyMarkup = '';
        foreach ($bodyBlocks as $index => $block) {
            if (!is_array($block)) {
                throw new InvalidArgumentException('Every page block must be an object.');
            }
            if (($block['type'] ?? null) === 'gallery') $block['media_alts'] = $mediaAlts;
            $bodyMarkup .= $this->renderBlock(
                $block,
                (int) $index,
                $mediaUrls,
                $supportedShapes,
                $structure,
                $language,
            );
        }

        $logoUrl = $presentation['brand_assets']['logo_url'] ?? null;
        $logo = is_string($logoUrl) && $logoUrl !== ''
            ? '<img src="' . self::escape(self::safeAssetUrl($logoUrl)) . '" alt="" width="40" height="40">'
            : '<span class="d-brand__mark" aria-hidden="true">✦</span>';

        $attributes = sprintf(
            'data-dendrila-theme="%s" data-dendrila-hero="%s" data-dendrila-alignment="%s" '
            . 'data-dendrila-gallery="%s" data-dendrila-media-crop="%s" data-dendrila-full-bleed="%s" '
            . 'data-dendrila-illustration-frequency="%s" data-dendrila-illustration-style="%s" '
            . 'data-dendrila-heading-style="%s" data-dendrila-body-style="%s"',
            self::escape($theme['id']),
            self::escape($structure['hero']),
            self::escape($structure['alignment']),
            self::escape($structure['gallery']),
            self::escape($structure['media_crop']),
            $structure['full_bleed'] ? 'true' : 'false',
            self::escape($structure['illustration_frequency']),
            self::escape($structure['illustration_style']),
            self::escape($structure['heading_style']),
            self::escape($structure['body_style']),
        );

        return '<!doctype html><html lang="' . self::escape($language) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . $this->seoHead->render($site, $page, $seo)
            . '<link rel="stylesheet" href="' . self::escape($themeCssUrl) . '">'
            . (isset($presentation['support_css_url']) ? '<link rel="stylesheet" href="'.self::escape(self::safeAssetUrl($presentation['support_css_url'])).'">' : '')
            . '<style data-dendrila-presentation>' . $compiledCss . '</style>'
            . '</head><body ' . $attributes . '>'
            . '<a class="d-skip-link" href="#contenu">' . ($language === 'en' ? 'Skip to content' : 'Aller au contenu') . '</a>'
            . '<header class="d-header"><div class="d-shell d-header__inner">'
            . '<a class="d-brand" href="' . self::escape($homeUrl) . '">' . $logo . '<span>' . self::escape($siteName) . '</span></a>'
            . $this->renderNavigation($navigation, $language)
            . '</div></header><main id="contenu">'
            . $this->renderHero($title, $tagline, $hero, $mediaUrls, $supportedShapes, $structure)
            // Minimal kit has a real two-column index, not a faux grid spread over
            // many implicit rows. Other themes keep their existing DOM structure.
            . ($theme['id'] === 'very-minimal' ? '<div class="d-main-content">' : '')
            . $bodyMarkup
            . ($theme['id'] === 'very-minimal' ? '</div>' : '')
            . '</main>'
            . $this->renderFooter($siteName, $navigation, $language)
            . $privacyMarkup
            . '</body></html>';
    }

    private function structure(array $presentation): array
    {
        $raw = is_array($presentation['structure'] ?? null) ? $presentation['structure'] : [];
        return [
            'hero' => self::enum($raw['hero'] ?? 'split', self::HERO_LAYOUTS, 'hero layout'),
            'alignment' => self::enum($raw['alignment'] ?? 'left', self::ALIGNMENTS, 'alignment'),
            'gallery' => self::enum($raw['gallery'] ?? 'grid', self::GALLERIES, 'gallery'),
            'media_crop' => self::enum($raw['media_crop'] ?? 'mixed', self::MEDIA_CROPS, 'media crop'),
            'full_bleed' => is_bool($raw['full_bleed'] ?? null) ? $raw['full_bleed'] : false,
            'accent_backgrounds' => self::enum($raw['accent_backgrounds'] ?? 'soft_sections', self::BACKGROUNDS, 'accent backgrounds'),
            'illustration_frequency' => self::enum($raw['illustration_frequency'] ?? 'occasional', self::ILLUSTRATION_FREQUENCIES, 'illustration frequency'),
            'illustration_style' => self::enum($raw['illustration_style'] ?? 'line', self::ILLUSTRATION_STYLES, 'illustration style'),
            'heading_style' => self::enum($raw['heading_style'] ?? 'neutral', self::HEADING_STYLES, 'heading style'),
            'body_style' => self::enum($raw['body_style'] ?? 'neutral', self::BODY_STYLES, 'body style'),
        ];
    }

    private function extractHero(array $blocks, string $heroLayout): array
    {
        $introIndex = null; $mediaIndex = null; $ctaIndex = null;
        foreach ($blocks as $index => $block) {
            if (!is_array($block) || !is_string($block['type'] ?? null)) continue;
            if ($introIndex === null && in_array($block['type'], ['text', 'rich_text'], true)) $introIndex = $index;
            if ($mediaIndex === null && in_array($heroLayout, ['split', 'media_first'], true) && $block['type'] === 'image') $mediaIndex = $index;
            if ($ctaIndex === null && $index <= 2 && $block['type'] === 'cta') $ctaIndex = $index;
        }
        $hero = [
            'intro' => $introIndex !== null ? $blocks[$introIndex] : null,
            'media' => $mediaIndex !== null ? $blocks[$mediaIndex] : null,
            'cta' => $ctaIndex !== null ? $blocks[$ctaIndex] : null,
        ];
        $consumed = array_filter([$introIndex, $mediaIndex, $ctaIndex], static fn ($value): bool => $value !== null);
        $body = [];
        foreach ($blocks as $index => $block) if (!in_array($index, $consumed, true)) $body[] = $block;
        return [$hero, $body];
    }

    private function renderHero(string $title, string $tagline, array $hero, array $mediaUrls, array $supportedShapes, array $structure): string
    {
        $intro = '';
        if (is_array($hero['intro'])) {
            $intro = '<p class="d-lead">' . nl2br(self::escape(self::longText($hero['intro']['text'] ?? null, 'hero text')), false) . '</p>';
        }
        $eyebrow = $tagline !== '' ? '<p class="d-eyebrow">' . self::escape($tagline) . '</p>' : '';
        $cta = is_array($hero['cta']) ? '<div class="d-actions">' . $this->renderCtaLink($hero['cta']) . '</div>' : '';
        $media = '';
        if (is_array($hero['media']) && $structure['hero'] !== 'minimal') {
            $media = '<div class="d-hero__media">' . $this->renderMediaFrame($hero['media'], $mediaUrls, $supportedShapes, true) . '</div>';
        }
        return '<section class="d-hero"><div class="d-shell d-hero__grid"><div class="d-hero__copy">'
            . $eyebrow . '<h1 class="d-title">' . self::escape($title) . '</h1>' . $intro . $cta
            . '</div>' . $media . '</div></section>';
    }

    private function renderBlock(array $block, int $index, array $mediaUrls, array $supportedShapes, array $structure, string $locale): string
    {
        $type = $block['type'] ?? null;
        if (!is_string($type)) throw new InvalidArgumentException('Block type is missing.');
        $sectionClass = 'd-section d-section--block-' . str_replace('_', '-', $type)
            . ($this->softSection($index, $type, $structure['accent_backgrounds']) ? ' d-section--soft' : '');
        return match ($type) {
            'text', 'rich_text' => '<section class="' . $sectionClass . '"><div class="d-shell d-prose"><p class="'
                . ($type === 'rich_text' ? 'd-rich-text' : '') . '">' . nl2br(self::escape(self::longText($block['text'] ?? null, 'text block')), false)
                . '</p></div></section>',
            'image' => '<section class="' . $sectionClass . '"><div class="d-shell">' . $this->renderImageFigure($block, $mediaUrls, $supportedShapes) . '</div></section>',
            'gallery' => '<section class="' . $sectionClass . '"><div class="d-shell">'
                . $this->renderSectionHeading($block) . $this->renderGallery($block, $mediaUrls, $supportedShapes) . '</div></section>',
            'cards' => '<section class="' . $sectionClass . '"><div class="d-shell">'
                . $this->renderSectionHeading($block) . $this->renderCards($block, $mediaUrls) . '</div></section>',
            'faq' => '<section class="' . $sectionClass . '"><div class="d-shell d-prose">'
                . $this->renderSectionHeading($block) . $this->renderFaq($block) . '</div></section>',
            'key_values' => '<section class="' . $sectionClass . '"><div class="d-shell">'
                . $this->renderSectionHeading($block) . $this->renderKeyValues($block) . '</div></section>',
            'statistics' => '<section class="' . $sectionClass . '"><div class="d-shell">'
                . $this->renderSectionHeading($block) . $this->renderStatistics($block) . '</div></section>',
            'cta' => '<section class="d-cta-band"><div class="d-shell d-cta-band__inner">'
                . $this->renderCtaLink($block) . '</div></section>',
            'embed_safe' => '<section class="' . $sectionClass . '"><div class="d-shell">'
                . $this->renderSectionHeading($block) . '<div class="d-embed">' . $this->renderEmbed($block, $locale)
                . '</div></div></section>',
            default => throw new InvalidArgumentException("Unsupported public block type '{$type}'."),
        };
    }

    private function renderSectionHeading(array $block): string
    {
        if (!isset($block['heading']) || !is_string($block['heading']) || trim($block['heading']) === '') {
            return '';
        }
        return '<div class="d-section__heading"><h2 class="d-heading">'
            . self::escape(self::shortText($block['heading'], 'section heading', 2000))
            . '</h2></div>';
    }

    private function renderCtaLink(array $block): string
    {
        return '<a class="d-button" href="' . self::escape(self::safeLinkUrl(self::shortText($block['url'] ?? null, 'CTA URL', 2000)))
            . '">' . self::escape(self::shortText($block['label'] ?? null, 'CTA label', 300)) . '</a>';
    }

    private function renderMediaFrame(array $block, array $mediaUrls, array $supportedShapes, bool $hero = false): string
    {
        $mediaId = self::shortText($block['media_id'] ?? null, 'media id', 128);
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $mediaId) || !isset($mediaUrls[$mediaId])) {
            throw new InvalidArgumentException("No public URL is available for media '{$mediaId}'.");
        }
        $shape = is_string($block['shape'] ?? null) && in_array($block['shape'], $supportedShapes, true) ? $block['shape'] : 'auto';
        $x = self::focal($block['focal_x'] ?? 50);
        $y = self::focal($block['focal_y'] ?? 50);
        $alt = isset($block['alt']) ? self::shortText($block['alt'], 'image alternative', 2000, true) : '';
        return '<div class="d-media" data-media-shape="' . self::escape($shape) . '" style="--d-media-x:' . $x . '%;--d-media-y:' . $y . '%;">'
            . '<img class="d-media__image" src="' . self::escape(self::safeAssetUrl($mediaUrls[$mediaId])) . '" alt="' . self::escape($alt)
            . '" loading="' . ($hero ? 'eager' : 'lazy') . '" decoding="async"' . ($hero ? ' fetchpriority="high"' : '') . '></div>';
    }

    private function renderImageFigure(array $block, array $mediaUrls, array $supportedShapes): string
    {
        $caption = isset($block['caption']) ? self::shortText($block['caption'], 'image caption', 2000, true) : '';
        return '<figure class="d-gallery__item">' . $this->renderMediaFrame($block, $mediaUrls, $supportedShapes)
            . ($caption !== '' ? '<figcaption class="d-caption">' . self::escape($caption) . '</figcaption>' : '') . '</figure>';
    }

    private function renderGallery(array $block, array $mediaUrls, array $supportedShapes): string
    {
        $ids = $block['media_ids'] ?? null;
        if (!is_array($ids) || !array_is_list($ids) || count($ids) > 100) throw new InvalidArgumentException('Gallery media_ids must be a bounded list.');
        $groupAlt = isset($block['alt']) ? self::shortText($block['alt'], 'gallery alternative', 2000, true) : '';
        $items = [];
        foreach ($ids as $index => $mediaId) {
            $items[] = '<figure class="d-gallery__item">' . $this->renderMediaFrame([
                'media_id' => $mediaId, 'alt' => $block['media_alts'][$mediaId] ?? ($index === 0 ? $groupAlt : ''), 'shape' => 'rounded', 'focal_x' => 50, 'focal_y' => 50,
            ], $mediaUrls, $supportedShapes) . '</figure>';
        }
        return '<div class="d-gallery">' . implode('', $items) . '</div>';
    }

    private function renderCards(array $block, array $mediaUrls): string
    {
        $markup = [];
        foreach (self::items($block, 'cards') as $item) {
            $title = self::shortText($item['title'] ?? null, 'card title', 1000);
            $media = '';
            if (is_string($item['media_id'] ?? null)) {
                if (!isset($mediaUrls[$item['media_id']])) throw new InvalidArgumentException("No public URL is available for card media '{$item['media_id']}'.");
                $media = '<div class="d-card__media"><img src="' . self::escape(self::safeAssetUrl($mediaUrls[$item['media_id']])) . '" alt="" loading="lazy" decoding="async"></div>';
            }
            $text = isset($item['text']) ? '<p>' . nl2br(self::escape(self::longText($item['text'], 'card text')), false) . '</p>' : '';
            $titleMarkup = isset($item['url'])
                ? '<h2 class="d-card__title"><a href="' . self::escape(self::safeLinkUrl((string) $item['url'])) . '">' . self::escape($title) . '</a></h2>'
                : '<h2 class="d-card__title">' . self::escape($title) . '</h2>';
            $markup[] = '<article class="d-card">' . $media . $titleMarkup . $text . '</article>';
        }
        return '<div class="d-card-grid">' . implode('', $markup) . '</div>';
    }

    private function renderFaq(array $block): string
    {
        $markup = [];
        foreach (self::items($block, 'FAQ') as $item) {
            $markup[] = '<details><summary>' . self::escape(self::shortText($item['question'] ?? null, 'FAQ question', 2000))
                . '</summary><p>' . nl2br(self::escape(self::longText($item['answer'] ?? null, 'FAQ answer')), false) . '</p></details>';
        }
        return '<div class="d-faq">' . implode('', $markup) . '</div>';
    }

    private function renderKeyValues(array $block): string
    {
        $markup = [];
        foreach (self::items($block, 'key/value') as $item) {
            $markup[] = '<div class="d-key-values__item"><dt>' . self::escape(self::shortText($item['label'] ?? null, 'key/value label', 2000))
                . '</dt><dd>' . self::escape(self::shortText($item['value'] ?? null, 'key/value value', 5000)) . '</dd></div>';
        }
        return '<dl class="d-key-values">' . implode('', $markup) . '</dl>';
    }

    private function renderStatistics(array $block): string
    {
        $markup = [];
        foreach (self::items($block, 'statistics') as $item) {
            $note = isset($item['note']) ? '<small class="d-stat__note">' . self::escape(self::shortText($item['note'], 'statistic note', 2000, true)) . '</small>' : '';
            $markup[] = '<div class="d-stat"><strong class="d-stat__value">' . self::escape(self::shortText($item['value'] ?? null, 'statistic value', 1000))
                . '</strong><span class="d-stat__label">' . self::escape(self::shortText($item['label'] ?? null, 'statistic label', 2000))
                . '</span>' . $note . '</div>';
        }
        return '<div class="d-stats">' . implode('', $markup) . '</div>';
    }

    private function renderEmbed(array $block, string $locale): string
    {
        $provider = self::shortText($block['provider'] ?? null, 'embed provider', 100);
        $fallback = match ($provider) {
            'youtube-nocookie' => 'YouTube',
            'vimeo' => 'Vimeo',
            'openstreetmap' => 'OpenStreetMap',
            default => throw new InvalidArgumentException('Unsupported embed provider.'),
        };
        $title = isset($block['label']) && is_string($block['label']) && trim($block['label']) !== ''
            ? self::shortText($block['label'], 'embed label', 190) : $fallback;
        return $this->consentResources->iframe('external_media', self::shortText($block['url'] ?? null, 'embed URL', 2000), $title, $locale);
    }

    private function renderNavigation(array $navigation, string $language): string
    {
        if (count($navigation) > 30) throw new InvalidArgumentException('Navigation is limited to 30 links.');
        $links = [];
        foreach ($navigation as $item) {
            if (!is_array($item)) throw new InvalidArgumentException('Invalid navigation item.');
            $links[] = '<a href="' . self::escape(self::safeLinkUrl(self::shortText($item['url'] ?? null, 'navigation URL', 2000)))
                . '">' . self::escape(self::shortText($item['label'] ?? null, 'navigation label', 190)) . '</a>';
        }
        return $links === [] ? '' : '<nav class="d-nav" aria-label="' . ($language === 'en' ? 'Main navigation' : 'Navigation principale') . '">' . implode('', $links) . '</nav>';
    }

    private function renderFooter(string $siteName, array $navigation, string $language): string
    {
        $links = [];
        foreach (array_slice($navigation, 0, 6) as $item) {
            if (!is_array($item) || !isset($item['label'], $item['url'])) continue;
            $links[] = '<a href="' . self::escape(self::safeLinkUrl((string) $item['url'])) . '">' . self::escape(self::shortText($item['label'], 'footer label', 190)) . '</a>';
        }
        return '<footer class="d-footer"><div class="d-shell d-footer__grid"><div><strong>' . self::escape($siteName) . '</strong></div>'
            . ($links !== [] ? '<nav aria-label="' . ($language === 'en' ? 'Secondary navigation' : 'Navigation secondaire') . '">' . implode('<br>', $links) . '</nav>' : '')
            . '</div></footer>';
    }

    private function softSection(int $index, string $type, string $mode): bool
    {
        return match ($mode) {
            'alternating' => $index % 2 === 1,
            'soft_sections' => in_array($type, ['cards', 'faq', 'key_values', 'statistics'], true),
            default => false,
        };
    }

    private static function enum(mixed $value, array $allowed, string $label): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) throw new InvalidArgumentException("Invalid {$label}.");
        return $value;
    }

    private static function items(array $block, string $label): array
    {
        $items = $block['items'] ?? null;
        if (!is_array($items) || !array_is_list($items) || count($items) > 50) throw new InvalidArgumentException("{$label} needs a bounded item list.");
        foreach ($items as $item) if (!is_array($item)) throw new InvalidArgumentException("Invalid {$label} item.");
        return $items;
    }

    private static function focal(mixed $value): string
    {
        if ((!is_int($value) && !is_float($value)) || $value < 0 || $value > 100) throw new InvalidArgumentException('Media focal points must be between 0 and 100.');
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    private static function shortText(mixed $value, string $label, int $max, bool $allowEmpty = false): string
    {
        if (!is_string($value) || (!$allowEmpty && trim($value) === '') || mb_strlen($value) > $max) throw new InvalidArgumentException("Invalid {$label}.");
        return trim($value);
    }

    private static function longText(mixed $value, string $label): string
    {
        if (!is_string($value) || strlen($value) > 100_000) throw new InvalidArgumentException("Invalid {$label}.");
        return $value;
    }

    private static function safeAssetUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) throw new InvalidArgumentException('Invalid public asset URL.');
        if (str_starts_with($url, '/')) return $url;
        if (filter_var($url, FILTER_VALIDATE_URL) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') return $url;
        throw new InvalidArgumentException('Public assets must use a root-relative or HTTPS URL.');
    }

    private static function safeLinkUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) throw new InvalidArgumentException('Invalid public link URL.');
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) return $url;
        if (str_starts_with(strtolower($url), 'mailto:')) {
            $email = substr($url, 7);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) return $url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL) && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') return $url;
        throw new InvalidArgumentException('Public links must be root-relative, fragment, mailto or HTTPS.');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
