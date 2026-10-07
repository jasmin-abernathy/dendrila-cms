<?php
declare(strict_types=1);

namespace Dendrila\Cms\Front;

use Dendrila\Cms\Seo\PdoSeoRepository;
use Dendrila\Cms\Seo\SeoMetadataDefinition;
use PDO;

final readonly class PdoPublicSeoSource implements PublicSeoSource
{
    private PdoSeoRepository $seo;

    public function __construct(PDO $pdo)
    {
        $this->seo = new PdoSeoRepository($pdo);
    }

    public function metadata(string $siteId, string $contentId): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,63}$/D', $siteId)
            || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $contentId)) {
            throw new \InvalidArgumentException('Invalid public SEO target.');
        }
        $record = $this->seo->find($siteId, $contentId);
        if ($record === null) return [];
        return SeoMetadataDefinition::normalize(array_intersect_key($record, array_flip([
            'title', 'description', 'canonical_url', 'social_title',
            'social_description', 'social_image_url', 'indexable',
        ])));
    }
}
