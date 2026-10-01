<?php
declare(strict_types=1);

namespace LePotager\Cms\Design;

use LePotager\Cms\Authorization\ActorContext;
use LePotager\Cms\Authorization\AuthorizationService;
use PDO;

/** Internal authenticated service. No new MMW v1 route is introduced. */
final readonly class PdoCompositionService
{
    public function __construct(private PDO $pdo, private AuthorizationService $authorization) {}

    public function saveDraft(ActorContext $actor, SiteComposition $composition, int $expectedVersion): int
    {
        $this->authorization->require($actor, 'composition.manage');
        return $this->mutate($actor, $expectedVersion, 'draft', $composition);
    }

    /** The reviewed composition must match the stored draft byte for byte. */
    public function publish(ActorContext $actor, SiteComposition $reviewed, int $expectedVersion): int
    {
        $this->authorization->require($actor, 'composition.publish');
        return $this->mutate($actor, $expectedVersion, 'publish', $reviewed);
    }

    public function draft(ActorContext $actor): ?array
    {
        $this->authorization->require($actor, 'composition.manage');
        $q = $this->pdo->prepare('SELECT version, draft_json, published_version FROM site_compositions WHERE site_id = :site');
        $q->execute(['site'=>$actor->siteId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['draft_json'] === null) return null;
        return ['version'=>(int) $row['version'], 'composition'=>json_decode($row['draft_json'], true, 64, JSON_THROW_ON_ERROR),
            'published_version'=>$row['published_version'] === null ? null : (int) $row['published_version']];
    }

    public function published(string $siteId): ?array
    {
        $q = $this->pdo->prepare('SELECT published_json FROM site_compositions WHERE site_id = :site');
        $q->execute(['site'=>$siteId]);
        $json = $q->fetchColumn();
        return !is_string($json) ? null : json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    }

    /** Return private snapshots to the caller for revalidation against today's catalogs. */
    public function revision(ActorContext $actor, int $version): ?array
    {
        $this->authorization->require($actor, 'composition.manage');
        $q = $this->pdo->prepare('SELECT snapshot_json FROM composition_revisions WHERE site_id = :site AND version = :version');
        $q->execute(['site'=>$actor->siteId, 'version'=>$version]);
        $json = $q->fetchColumn();
        return !is_string($json) ? null : json_decode($json, true, 64, JSON_THROW_ON_ERROR);
    }

    /** Restoring writes a new draft; publishing remains a separate explicit operation. */
    public function restoreDraft(ActorContext $actor, SiteComposition $revalidated, int $expectedVersion): int
    {
        $this->authorization->require($actor, 'composition.manage');
        return $this->mutate($actor, $expectedVersion, 'restore', $revalidated);
    }

    private function mutate(ActorContext $actor, int $expectedVersion, string $operation, SiteComposition $composition): int
    {
        if ($expectedVersion < 0 || $expectedVersion === PHP_INT_MAX) throw new \InvalidArgumentException('Invalid composition version.');
        if ($this->pdo->inTransaction()) throw new \LogicException('Composition service owns its transaction.');
        $this->pdo->beginTransaction();
        try {
            // Lock the site row first: serializes first insert as well as later updates.
            $site = $this->pdo->prepare('SELECT site_id FROM sites WHERE site_id = :site FOR UPDATE');
            $site->execute(['site'=>$actor->siteId]);
            if ($site->fetchColumn() === false) throw new \RuntimeException('Site does not exist.');
            $this->authorization->require($actor, $operation === 'publish' ? 'composition.publish' : 'composition.manage');
            $q = $this->pdo->prepare('SELECT version, draft_json FROM site_compositions WHERE site_id = :site FOR UPDATE');
            $q->execute(['site'=>$actor->siteId]);
            $row = $q->fetch(PDO::FETCH_ASSOC);
            $version = $row ? (int) $row['version'] : 0;
            if ($version !== $expectedVersion) throw new \RuntimeException('Composition version conflict.');
            $json = $composition->json();
            if ($operation === 'publish' && (!$row || $row['draft_json'] !== $json)) {
                throw new \RuntimeException('Reviewed composition differs from the current draft.');
            }
            $next = $version + 1;
            if (!$row) {
                $q = $this->pdo->prepare('INSERT INTO site_compositions (site_id, version, draft_json, updated_at) VALUES (:site, :version, :json, UTC_TIMESTAMP(6))');
            } elseif ($operation === 'publish') {
                $q = $this->pdo->prepare('UPDATE site_compositions SET version = :version, published_json = :json, published_version = :published_version, updated_at = UTC_TIMESTAMP(6) WHERE site_id = :site');
            } else {
                $q = $this->pdo->prepare('UPDATE site_compositions SET version = :version, draft_json = :json, updated_at = UTC_TIMESTAMP(6) WHERE site_id = :site');
            }
            $params = ['site'=>$actor->siteId, 'version'=>$next, 'json'=>$json];
            if ($operation === 'publish') $params['published_version'] = $next;
            $q->execute($params);
            $q = $this->pdo->prepare('INSERT INTO composition_revisions (site_id, version, operation, snapshot_json, actor_id, created_at) VALUES (:site, :version, :operation, :json, :actor, UTC_TIMESTAMP(6))');
            $q->execute(['site'=>$actor->siteId,'version'=>$next,'operation'=>$operation,'json'=>$json,'actor'=>$actor->actorId]);
            $q = $this->pdo->prepare("INSERT INTO audit_events (site_id, actor_id, device_id, action, entity_type, entity_id, outcome, details_json, created_at) VALUES (:site, :actor, :device, :action, 'composition', :entity, 'allowed', :details, UTC_TIMESTAMP(6))");
            $q->execute(['site'=>$actor->siteId,'actor'=>$actor->actorId,'device'=>$actor->deviceId,
                'action'=>'composition.' . $operation,'entity'=>$actor->siteId,
                'details'=>json_encode(['version'=>$next], JSON_THROW_ON_ERROR)]);
            $this->pdo->commit();
            return $next;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }
}
