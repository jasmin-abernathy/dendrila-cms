<?php
declare(strict_types=1);

namespace Dendrila\Cms\Seo;

final class SitemapBuilder
{
    /**
     * @param list<array{url:string,last_modified?:string,indexable?:bool}> $entries
     */
    public function render(array $entries): string
    {
        if (!array_is_list($entries) || count($entries) > 50000) {
            throw new \InvalidArgumentException('Sitemap entries must be a bounded list.');
        }

        $urls = [];
        $seen = [];
        foreach ($entries as $entry) {
            if (!is_array($entry) || !is_string($entry['url'] ?? null)) {
                throw new \InvalidArgumentException('Invalid sitemap entry.');
            }
            if (($entry['indexable'] ?? true) === false) {
                continue;
            }
            if (isset($entry['indexable']) && !is_bool($entry['indexable'])) {
                throw new \InvalidArgumentException('Invalid sitemap indexable flag.');
            }
            $url = trim($entry['url']);
            if (!$this->httpsUrl($url)) {
                throw new \InvalidArgumentException('Sitemap URLs must use HTTPS.');
            }
            if (isset($seen[$url])) continue;
            $seen[$url] = true;

            $lastModified = null;
            if (isset($entry['last_modified'])) {
                if (!is_string($entry['last_modified'])
                    || preg_match('/^\d{4}-\d{2}-\d{2}$/D', $entry['last_modified']) !== 1) {
                    throw new \InvalidArgumentException('Sitemap last_modified must use YYYY-MM-DD.');
                }
                $lastModified = $entry['last_modified'];
            }
            $urls[] = '<url><loc>' . self::escape($url) . '</loc>'
                . ($lastModified !== null ? '<lastmod>' . $lastModified . '</lastmod>' : '')
                . '</url>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . implode('', $urls)
            . '</urlset>';
    }

    private function httpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https';
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
