<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use InvalidArgumentException;

/** Generic, optional and theme-independent launcher; it never loads a third party before click. */
final class ContactEntryRenderer
{
    public function render(array $config, string $locale = 'fr'): string
    {
        $mode = $config['mode'] ?? 'none';
        if ($mode === 'none') return '';
        $type = $config['presentation'] ?? 'button';
        if (!in_array($mode, ['direct', 'form', 'guided'], true)
            || !in_array($type, ['button', 'link', 'card', 'banner', 'floating'], true)) {
            throw new InvalidArgumentException('Invalid contact entry mode or display.');
        }
        $defaults = $locale === 'en'
            ? ['direct' => 'Contact us', 'form' => 'Send a message', 'guided' => 'Guided enquiry']
            : ['direct' => 'Nous contacter', 'form' => 'Écrire un message', 'guided' => 'Parcours guidé'];
        $label = $config['label'] ?? $defaults[$mode];
        $description = $config['description'] ?? '';
        $url = $config['url'] ?? null;
        if (!is_string($label) || trim($label) === '' || mb_strlen($label) > 80
            || !is_string($description) || mb_strlen($description) > 240
            || !is_string($url) || !self::safeUrl($url)) {
            throw new InvalidArgumentException('Invalid contact entry content or URL.');
        }
        // CSS remains local to this reusable component, and follows each theme's brand variables.
        $css = '.d-contact-entry{color:var(--dendrila-color-text,inherit);font-family:inherit}'
            . '.d-contact-entry__description{max-width:46rem;color:var(--dendrila-color-muted,inherit)}'
            . '.d-contact-entry__link{display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;font-weight:750}'
            . '.d-contact-entry__link:focus-visible{outline:3px solid currentColor;outline-offset:4px}'
            . '.d-contact-entry--button,.d-contact-entry--link,.d-contact-entry--card,.d-contact-entry--banner{'
            . 'width:min(calc(100% - 2rem),var(--dendrila-content-max,68rem));margin:2rem auto}'
            . '.d-contact-entry--button .d-contact-entry__link,.d-contact-entry--card .d-contact-entry__link,'
            . '.d-contact-entry--banner .d-contact-entry__link,.d-contact-entry--floating .d-contact-entry__link{'
            . 'padding:.8rem 1.2rem;border-radius:.4rem;background:var(--dendrila-color-primary,#245e43);color:#fff}'
            . '.d-contact-entry--card,.d-contact-entry--banner{display:flex;align-items:center;justify-content:space-between;'
            . 'gap:1.5rem;padding:1.6rem;border:1px solid var(--dendrila-color-border,#ddd);'
            . 'background:var(--dendrila-color-surface,#fff);border-radius:.9rem}'
            . '.d-contact-entry--banner{border-radius:0}'
            . '.d-contact-entry--floating{position:fixed;z-index:90;right:1rem;bottom:1rem;'
            . 'max-width:min(24rem,calc(100% - 2rem));padding:.75rem;'
            . 'background:var(--dendrila-color-surface,#fff);border:1px solid var(--dendrila-color-border,#ddd);'
            . 'border-radius:.9rem;box-shadow:0 8px 28px #0002}'
            . '@media(max-width:38rem){.d-contact-entry--card,.d-contact-entry--banner{'
            . 'flex-direction:column;align-items:flex-start}.d-contact-entry--floating{'
            . 'position:static;max-width:none;width:calc(100% - 2rem);margin:2rem auto;box-shadow:none}}'
            . '@media(prefers-reduced-motion:reduce){.d-contact-entry *{transition:none!important}}';
        $text = $description === '' ? '' : '<p class="d-contact-entry__description">'
            . self::escape($description) . '</p>';
        return '<style data-dendrila-contact-entry>' . $css . '</style>'
            . '<aside class="d-contact-entry d-contact-entry--' . $type . '" data-dendrila-contact-mode="'
            . $mode . '" aria-label="' . self::escape($label) . '">'
            . $text . '<a class="d-contact-entry__link" href="' . self::escape($url) . '">'
            . self::escape($label) . '<span aria-hidden="true">→</span></a></aside>';
    }

    private static function safeUrl(string $url): bool
    {
        if ($url === '' || trim($url) !== $url || str_starts_with($url, '//')
            || str_contains($url, '\\') || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) return false;
        if (str_starts_with($url, '/')) return true;
        if (str_starts_with($url, '#')) return strlen($url) > 1;
        if (str_starts_with(strtolower($url), 'mailto:')) {
            return filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL) !== false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
