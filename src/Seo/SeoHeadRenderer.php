<?php
declare(strict_types=1);

namespace Dendrila\Cms\Seo;

final class SeoHeadRenderer
{
    /** @param array<string,mixed> $site @param array<string,mixed> $page @param array<string,mixed> $metadata */
    public function render(array $site, array $page, array $metadata): string
    {
        $metadata = SeoMetadataDefinition::normalize($metadata);
        $siteName = $this->text($site['name'] ?? null, 190, 'site name');
        $pageTitle = $this->text($page['title'] ?? null, 300, 'page title');
        $title = $metadata['title'] ?? ($pageTitle . ' — ' . $siteName);

        $canonical = $metadata['canonical_url'];
        if (is_string($canonical) && str_starts_with($canonical, '/')) {
            $base = $site['canonical_base_url'] ?? null;
            if (!is_string($base) || !$this->httpsUrl($base)) {
                throw new \InvalidArgumentException('A root-relative canonical URL requires site canonical_base_url.');
            }
            $canonical = rtrim($base, '/') . $canonical;
        }

        $socialImage = $metadata['social_image_url'];
        if (is_string($socialImage) && str_starts_with($socialImage, '/')) {
            $base = $site['canonical_base_url'] ?? null;
            if (!is_string($base) || !$this->httpsUrl($base)) {
                throw new \InvalidArgumentException('A root-relative social image requires site canonical_base_url.');
            }
            $socialImage = rtrim($base, '/') . $socialImage;
        }

        $parts = ['<title>' . self::escape($title) . '</title>'];
        if ($metadata['description'] !== null) {
            $parts[] = '<meta name="description" content="' . self::escape($metadata['description']) . '">';
        }
        if ($canonical !== null) {
            $parts[] = '<link rel="canonical" href="' . self::escape($canonical) . '">';
        }
        if ($metadata['indexable'] !== true) {
            $parts[] = '<meta name="robots" content="noindex,follow">';
        }

        $ogTitle = $metadata['social_title'] ?? $title;
        $parts[] = '<meta property="og:title" content="' . self::escape($ogTitle) . '">';
        $parts[] = '<meta property="og:type" content="website">';
        if ($metadata['social_description'] !== null || $metadata['description'] !== null) {
            $parts[] = '<meta property="og:description" content="'
                . self::escape($metadata['social_description'] ?? $metadata['description']) . '">';
        }
        if ($canonical !== null) {
            $parts[] = '<meta property="og:url" content="' . self::escape($canonical) . '">';
        }
        if ($socialImage !== null) {
            $parts[] = '<meta property="og:image" content="' . self::escape($socialImage) . '">';
        }
        return implode('', $parts);
    }

    private function text(mixed $value, int $max, string $label): string
    {
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $max) {
            throw new \InvalidArgumentException("Invalid {$label}.");
        }
        return trim($value);
    }

    private function httpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
