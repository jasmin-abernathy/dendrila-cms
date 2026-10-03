<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

final class ConsentResourceRenderer
{
    /** @var list<string> */
    private const CATEGORIES = ['statistics', 'marketing', 'external_media', 'functional', 'unknown'];

    public function script(string $category, string $src, bool $async = false, bool $defer = false): string
    {
        self::assertCategory($category);
        $src = self::safeUrl($src);
        return sprintf(
            '<script type="text/plain" data-dendrila-consent-category="%s" data-dendrila-src="%s"%s%s></script>',
            self::escape($category),
            self::escape($src),
            $async ? ' data-dendrila-async="1"' : '',
            $defer ? ' data-dendrila-defer="1"' : '',
        );
    }

    public function inlineScript(string $category, string $javascript): string
    {
        self::assertCategory($category);
        if (strlen($javascript) > 262_144) {
            throw new \InvalidArgumentException('Deferred inline script is too large.');
        }
        return sprintf(
            '<script type="text/plain" data-dendrila-consent-category="%s" data-dendrila-inline-base64="%s"></script>',
            self::escape($category),
            self::escape(base64_encode($javascript)),
        );
    }

    public function stylesheet(string $category, string $href, string $media = 'all'): string
    {
        self::assertCategory($category);
        $href = self::safeUrl($href);
        if (!preg_match('/^[a-zA-Z0-9 (),.:_-]{1,120}$/', $media)) {
            throw new \InvalidArgumentException('Invalid deferred stylesheet media query.');
        }
        return sprintf(
            '<link rel="preload" as="style" data-dendrila-consent-category="%s" data-dendrila-href="%s" data-dendrila-media="%s">',
            self::escape($category),
            self::escape($href),
            self::escape($media),
        );
    }

    public function iframe(
        string $category,
        string $src,
        string $title,
        string $locale = 'fr',
    ): string {
        self::assertCategory($category);
        $src = self::safeUrl($src);
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 190) {
            throw new \InvalidArgumentException('A deferred iframe needs a short title.');
        }
        $copy = $locale === 'en'
            ? ['blocked' => 'This external content is blocked until you allow its category.', 'allow' => 'Allow this content']
            : ['blocked' => 'Ce contenu externe reste bloqué tant que vous n’autorisez pas sa catégorie.', 'allow' => 'Autoriser ce contenu'];

        return sprintf(
            '<div class="dendrila-privacy-placeholder" data-dendrila-consent-placeholder="%s">'
            . '<div data-dendrila-placeholder-copy><p>%s</p>'
            . '<button type="button" data-dendrila-consent-allow="%s">%s</button></div>'
            . '<iframe data-dendrila-consent-category="%s" data-dendrila-src="%s" title="%s" loading="lazy" hidden></iframe>'
            . '</div>',
            self::escape($category),
            self::escape($copy['blocked']),
            self::escape($category),
            self::escape($copy['allow']),
            self::escape($category),
            self::escape($src),
            self::escape($title),
        );
    }

    private static function assertCategory(string $category): void
    {
        if (!in_array($category, self::CATEGORIES, true)) {
            throw new \InvalidArgumentException('Unknown consent category.');
        }
    }

    private static function safeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) {
            throw new \InvalidArgumentException('Invalid deferred resource URL.');
        }
        if (str_starts_with($url, '/')) {
            return $url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') {
            return $url;
        }
        throw new \InvalidArgumentException('Deferred resources must use a root-relative or HTTPS URL.');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
