<?php
declare(strict_types=1);

namespace LePotager\Cms\Content;

use DateTimeImmutable;
use DateTimeZone;
use LePotager\Cms\Storage\CmsStore;

/** Internal cron entrypoint. Never expose this method as a public HTTP route. */
final readonly class ScheduledPublisher
{
    public function __construct(private CmsStore $store)
    {
    }

    /** @return list<array<string, mixed>> */
    public function runForSite(string $siteId, ?DateTimeImmutable $now = null, int $limit = 25): array
    {
        $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('UTC'));
        $nowSql = $now->format('Y-m-d H:i:s.u');
        $due = $this->store->findDuePublications($siteId, $nowSql, max(1, min(100, $limit)));
        $published = [];
        foreach ($due as $candidate) {
            $result = $this->store->transaction(function () use ($siteId, $candidate, $nowSql): ?array {
                $content = $this->store->findContentForUpdate($siteId, $candidate['content_id']);
                if ($content === null || $content['status'] !== 'scheduled'
                    || $content['publish_at'] === null || $content['publish_at'] > $nowSql) {
                    return null;
                }
                $policy = $this->store->findSitePolicy($siteId);
                if (($policy['review_before_publish'] ?? false) === true
                    && ($content['review_approved_at'] ?? null) === null) {
                    $content['status'] = 'review';
                    $content['publish_at'] = null;
                    $content['review_approved_at'] = null;
                    $event = 'content.pending_review';
                } else {
                    $content['status'] = 'published';
                    $content['published_values'] = $content['values'];
                    $content['published_slug'] = $content['slug'];
                    $content['published_at'] = $nowSql;
                    $content['publish_at'] = null;
                    $this->store->incrementDataRevision($siteId);
                    $event = 'content.published_by_schedule';
                }
                $oldVersion = $content['version'];
                $content['version'] = $oldVersion + 1;
                $content['updated_at'] = $nowSql;
                $revisionId = bin2hex(random_bytes(16));
                $content['current_revision_id'] = $revisionId;
                if ($event === 'content.published_by_schedule') {
                    $content['published_version'] = $content['version'];
                    $content['published_revision_id'] = $revisionId;
                }
                $this->store->saveContent($content, $oldVersion);
                $this->store->appendRevision([
                    'site_id' => $siteId,
                    'revision_id' => $revisionId,
                    'content_id' => $content['content_id'],
                    'version' => $content['version'],
                    'author_id' => null,
                    'source' => 'system',
                    'change_summary' => $event === 'content.published_by_schedule'
                        ? 'Publication planifiée' : 'Revue requise après changement de politique',
                    'snapshot' => [
                        'type' => $content['type_id'], 'slug' => $content['slug'],
                        'status' => $content['status'], 'values' => $content['values'],
                        'published_slug' => $content['published_slug'],
                        'published_version' => $content['published_version'],
                        'published_revision_id' => $content['published_revision_id'],
                        'published_values' => $content['published_values'], 'publish_at' => $content['publish_at'],
                        'published_at' => $content['published_at'],
                        'review_approved_at' => $content['review_approved_at'] ?? null,
                    ],
                    'created_at' => $nowSql,
                ]);
                $this->store->appendAudit($siteId, null, null, $event, 'content', $content['content_id'], 'allowed', [
                    'version' => $content['version'], 'source' => 'scheduler',
                ]);
                return $content;
            });
            if ($result !== null && $result['status'] === 'published') {
                $published[] = $result;
            }
        }
        return $published;
    }
}
