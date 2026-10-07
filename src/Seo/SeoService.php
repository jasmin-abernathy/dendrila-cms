<?php
declare(strict_types=1);

namespace Dendrila\Cms\Seo;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\ContentNotFound;
use Dendrila\Cms\Content\VersionConflict;
use Dendrila\Cms\Storage\CmsStore;

final readonly class SeoService
{
    public function __construct(
        private SeoRepository $seo,
        private CmsStore $store,
        private AuthorizationService $authorization,
    ) {}

    /** @return array<string,mixed>|null */
    public function get(ActorContext $actor, string $contentId): ?array
    {
        $this->authorization->require($actor, 'content.read');
        $this->assertContentExists($actor, $contentId);
        return $this->seo->find($actor->siteId, $contentId);
    }

    /**
     * expectedVersion=0 creates metadata for a content item.
     * @param array<string,mixed> $metadata
     * @return array{seo:array<string,mixed>,site_revision:int}
     */
    public function save(ActorContext $actor, string $contentId, array $metadata, int $expectedVersion): array
    {
        $this->authorization->require($actor, 'seo.manage');
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $contentId) || $expectedVersion < 0) {
            throw new \InvalidArgumentException('Invalid SEO mutation target.');
        }
        $metadata = SeoMetadataDefinition::normalize($metadata);

        return $this->store->transaction(function () use ($actor, $contentId, $metadata, $expectedVersion): array {
            if ($this->store->findContentForUpdate($actor->siteId, $contentId) === null) {
                throw new ContentNotFound('SEO target content does not exist.');
            }
            $current = $this->seo->findForUpdate($actor->siteId, $contentId);
            if ($current === null && $expectedVersion !== 0) {
                throw new VersionConflict('SEO metadata no longer exists.');
            }
            if ($current !== null && (int) $current['version'] !== $expectedVersion) {
                throw new VersionConflict('SEO metadata version is stale.');
            }

            $record = array_merge($metadata, [
                'site_id' => $actor->siteId,
                'content_id' => $contentId,
                'version' => $current === null ? 1 : $expectedVersion + 1,
            ]);
            if ($current === null) {
                $this->seo->insert($record);
            } else {
                $this->seo->save($record, $expectedVersion);
            }

            $siteRevision = $this->store->incrementDataRevision($actor->siteId);
            $this->store->appendAudit(
                $actor->siteId,
                $actor->actorId,
                $actor->deviceId,
                $current === null ? 'seo.create' : 'seo.update',
                'seo_metadata',
                $contentId,
                'allowed',
                ['version' => $record['version'], 'indexable' => $record['indexable']],
            );
            return ['seo' => $record, 'site_revision' => $siteRevision];
        });
    }

    private function assertContentExists(ActorContext $actor, string $contentId): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $contentId)) {
            throw new ContentNotFound('SEO target content does not exist.');
        }
        $includeDrafts = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.read_draft');
        $ownDrafts = !$includeDrafts
            && $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'content.read_own_draft');
        if ($this->store->findContent(
            $actor->siteId,
            $contentId,
            $includeDrafts,
            $ownDrafts ? $actor->actorId : null,
        ) === null) {
            throw new ContentNotFound('SEO target content does not exist.');
        }
    }
}
