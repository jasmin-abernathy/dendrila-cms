<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Front\PublicPageRenderer;
use Dendrila\Cms\Front\PublicPageSource;

final readonly class FrontPageDocumentCollector implements PrivacyDocumentCollector
{
    public function __construct(
        private PublicPageRenderer $renderer,
        private PublicPageSource $source,
    ) {}

    public function collect(ActorContext $actor, string $mode): array
    {
        if (!in_array($mode, ['standard', 'full'], true)) {
            throw new \InvalidArgumentException('Unknown public front collection mode.');
        }
        $pages = $this->source->pages($actor, $mode);
        $limit = $mode === 'full' ? 100 : 30;
        if (!is_array($pages) || !array_is_list($pages) || count($pages) > $limit) {
            throw new \InvalidArgumentException('Public page source returned too many documents.');
        }

        $documents = [];
        $seen = [];
        foreach ($pages as $entry) {
            if (!is_array($entry)
                || !is_string($entry['id'] ?? null)
                || trim($entry['id']) === ''
                || strlen($entry['id']) > 190
                || isset($seen[$entry['id']])
                || !is_array($entry['site'] ?? null)
                || !is_array($entry['page'] ?? null)
                || !is_array($entry['presentation'] ?? null)
                || !is_string($entry['theme_css_url'] ?? null)) {
                throw new \InvalidArgumentException('Invalid public page source document.');
            }
            $seen[$entry['id']] = true;
            $content = $this->renderer->render(
                $entry['site'],
                $entry['page'],
                $entry['presentation'],
                $entry['theme_css_url'],
                is_array($entry['media_urls'] ?? null) ? $entry['media_urls'] : [],
                is_array($entry['navigation'] ?? null) ? $entry['navigation'] : [],
            );
            if (strlen($content) > 2_000_000) {
                throw new \InvalidArgumentException('Rendered public document is too large for Privacy inspection.');
            }
            $document = ['id' => $entry['id'], 'content' => $content];
            if (isset($entry['url'])) {
                if (!is_string($entry['url']) || strlen($entry['url']) > 2000) {
                    throw new \InvalidArgumentException('Invalid public document URL.');
                }
                $document['url'] = $entry['url'];
            }
            $documents[] = $document;
        }
        return $documents;
    }
}
