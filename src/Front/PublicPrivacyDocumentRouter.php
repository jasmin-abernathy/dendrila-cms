<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use InvalidArgumentException;

final readonly class PublicPrivacyDocumentRouter
{
    /** @var array<string,string> */
    public const ROUTES = [
        '/mentions-legales' => 'legal_notice',
        '/confidentialite' => 'privacy',
        '/cookies' => 'cookies',
    ];

    public function __construct(private PublicPrivacyDocumentSource $source) {}

    /** @return array<string,string> */
    public static function routes(): array
    {
        return self::ROUTES;
    }

    public static function pathForKind(string $kind): string
    {
        $path = array_search($kind, self::ROUTES, true);
        if (!is_string($path)) {
            throw new InvalidArgumentException('Unknown privacy public document kind.');
        }
        return $path;
    }

    public function render(
        string $siteId,
        string $path,
        array $site,
        array $presentation,
        string $themeCssUrl,
        array $navigation = [],
        string $privacyMarkup = '',
    ): ?string {
        $path = self::normalizePath($path);
        $kind = self::ROUTES[$path] ?? null;
        if ($kind === null) {
            return null;
        }

        $document = $this->source->find($siteId, $kind);
        if ($document === null) {
            return null;
        }
        if (($document['kind'] ?? null) !== $kind || !is_string($document['html'] ?? null)) {
            throw new InvalidArgumentException('Invalid public privacy document.');
        }
        $documentHtml = $document['html'];
        if (strlen($documentHtml) > 500_000
            || preg_match('~<(?:script|style|iframe|object|embed|html|body)\b~i', $documentHtml) === 1) {
            throw new InvalidArgumentException('Unsafe public privacy document markup.');
        }

        $siteName = self::shortText($site['name'] ?? null, 'site name', 190);
        $language = in_array($site['language'] ?? 'fr', ['fr', 'en'], true) ? (string) ($site['language'] ?? 'fr') : 'fr';
        $homeUrl = self::safeLinkUrl((string) ($site['home_url'] ?? '/'));
        $title = self::title($kind);
        $themeCssUrl = self::safeAssetUrl($themeCssUrl);
        $theme = $presentation['design_kit'] ?? null;
        if (!is_array($theme) || !is_string($theme['id'] ?? null)
            || preg_match('/^[a-z][a-z0-9-]*$/', $theme['id']) !== 1) {
            throw new InvalidArgumentException('Invalid presentation design kit.');
        }
        $compiledCss = $presentation['compiled_css'] ?? '';
        if (!is_string($compiledCss) || strlen($compiledCss) > 100_000
            || str_contains(strtolower($compiledCss), '</style')) {
            throw new InvalidArgumentException('Invalid compiled presentation CSS.');
        }
        if (strlen($privacyMarkup) > 500_000
            || str_contains(strtolower($privacyMarkup), '<html')
            || str_contains(strtolower($privacyMarkup), '</body')) {
            throw new InvalidArgumentException('Invalid privacy frontend markup.');
        }

        $links = self::navigation($navigation);
        $legalLinks = [];
        foreach (self::ROUTES as $route => $routeKind) {
            $legalLinks[] = '<a href="' . self::escape($route) . '">' . self::escape(self::title($routeKind)) . '</a>';
        }

        return '<!doctype html><html lang="' . self::escape($language) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . self::escape($title . ' — ' . $siteName) . '</title>'
            . '<link rel="stylesheet" href="' . self::escape($themeCssUrl) . '">'
            . '<style data-dendrila-presentation>' . $compiledCss . '</style>'
            . '</head><body data-dendrila-theme="' . self::escape($theme['id']) . '">'
            . '<a class="d-skip-link" href="#contenu">' . ($language === 'en' ? 'Skip to content' : 'Aller au contenu') . '</a>'
            . '<header class="d-header"><div class="d-shell d-header__inner">'
            . '<a class="d-brand" href="' . self::escape($homeUrl) . '"><span>' . self::escape($siteName) . '</span></a>'
            . ($links !== [] ? '<nav class="d-nav" aria-label="' . ($language === 'en' ? 'Main navigation' : 'Navigation principale') . '">' . implode('', $links) . '</nav>' : '')
            . '</div></header>'
            . '<main id="contenu"><section class="d-section"><div class="d-shell d-prose">'
            . $documentHtml
            . '</div></section></main>'
            . '<footer class="d-footer"><div class="d-shell d-footer__grid"><strong>' . self::escape($siteName) . '</strong>'
            . '<nav aria-label="' . ($language === 'en' ? 'Legal information' : 'Informations légales') . '">' . implode('<br>', $legalLinks) . '</nav>'
            . '</div></footer>'
            . $privacyMarkup
            . '</body></html>';
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim(trim($path), '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    private static function title(string $kind): string
    {
        return match ($kind) {
            'legal_notice' => 'Mentions légales',
            'privacy' => 'Politique de confidentialité',
            'cookies' => 'Politique cookies et traceurs',
            default => throw new InvalidArgumentException('Unknown privacy public document kind.'),
        };
    }

    /** @return list<string> */
    private static function navigation(array $navigation): array
    {
        if (count($navigation) > 30) {
            throw new InvalidArgumentException('Navigation is limited to 30 links.');
        }
        $links = [];
        foreach ($navigation as $item) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Invalid navigation item.');
            }
            $links[] = '<a href="' . self::escape(self::safeLinkUrl(self::shortText($item['url'] ?? null, 'navigation URL', 2000)))
                . '">' . self::escape(self::shortText($item['label'] ?? null, 'navigation label', 190)) . '</a>';
        }
        return $links;
    }

    private static function shortText(mixed $value, string $label, int $max): string
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Invalid {$label}.");
        }
        return trim($value);
    }

    private static function safeAssetUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) {
            throw new InvalidArgumentException('Invalid public asset URL.');
        }
        if (str_starts_with($url, '/')) {
            return $url;
        }
        if (filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') {
            return $url;
        }
        throw new InvalidArgumentException('Public assets must use a root-relative or HTTPS URL.');
    }

    private static function safeLinkUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '//')) {
            throw new InvalidArgumentException('Invalid public link URL.');
        }
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return $url;
        }
        if (str_starts_with(strtolower($url), 'mailto:')) {
            $email = substr($url, 7);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                return $url;
            }
        }
        if (filter_var($url, FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') {
            return $url;
        }
        throw new InvalidArgumentException('Public links must be root-relative, fragment, mailto or HTTPS.');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
