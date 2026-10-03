<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

use PDO;
use RuntimeException;

final readonly class PdoPrivacyRepository implements PrivacyRepository
{
    public function __construct(private PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public function transaction(callable $operation): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $result = $operation();
            if ($ownsTransaction) {
                $this->pdo->commit();
            }
            return $result;
        } catch (\Throwable $error) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function findSettings(string $siteId): ?array
    {
        $statement = $this->pdo->prepare('SELECT settings_json FROM privacy_settings WHERE site_id = :site_id');
        $statement->execute(['site_id' => $siteId]);
        $json = $statement->fetchColumn();
        if (!is_string($json)) {
            return null;
        }
        $value = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new RuntimeException('Stored privacy settings must be a JSON object.');
        }
        return $value;
    }

    public function saveSettings(string $siteId, array $settings): void
    {
        $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $statement = $this->pdo->prepare(
            'INSERT INTO privacy_settings (site_id, settings_json, created_at, updated_at)
             VALUES (:site_id, :settings_json, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
             ON DUPLICATE KEY UPDATE settings_json = VALUES(settings_json), updated_at = UTC_TIMESTAMP(6)'
        );
        $statement->execute(['site_id' => $siteId, 'settings_json' => $json]);
    }

    public function findLatestCompletedScan(string $siteId): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT scan_id, mode, status, started_by_id, source, summary_json, started_at, finished_at
             FROM privacy_scans WHERE site_id = :site_id AND status = 'completed'
             ORDER BY finished_at DESC, scan_id DESC LIMIT 1"
        );
        $statement->execute(['site_id' => $siteId]);
        $row = $statement->fetch();
        if (!is_array($row)) {
            return null;
        }
        $summary = json_decode((string) $row['summary_json'], true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($summary)) {
            throw new RuntimeException('Stored privacy scan summary is invalid.');
        }
        return [
            'scan_id' => $row['scan_id'],
            'mode' => $row['mode'],
            'status' => $row['status'],
            'started_by_id' => $row['started_by_id'],
            'source' => $row['source'],
            'summary' => $summary,
            'started_at' => $row['started_at'],
            'finished_at' => $row['finished_at'],
        ];
    }

    public function listFindings(string $siteId, string $scanId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT finding_id, kind, service_id, label, category, status, optional_tracking, evidence_json
             FROM privacy_findings WHERE site_id = :site_id AND scan_id = :scan_id
             ORDER BY label ASC, finding_id ASC LIMIT 1000'
        );
        $statement->execute(['site_id' => $siteId, 'scan_id' => $scanId]);
        $rows = [];
        foreach ($statement->fetchAll() as $row) {
            $evidence = json_decode((string) $row['evidence_json'], true, 64, JSON_THROW_ON_ERROR);
            $rows[] = [
                'finding_id' => $row['finding_id'],
                'kind' => $row['kind'],
                'service_id' => $row['service_id'],
                'label' => $row['label'],
                'category' => $row['category'],
                'status' => $row['status'],
                'optional_tracking' => (bool) $row['optional_tracking'],
                'evidence' => is_array($evidence) ? $evidence : [],
            ];
        }
        return $rows;
    }

    public function saveCompletedScan(array $scan, array $findings): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO privacy_scans
             (site_id, scan_id, mode, status, started_by_id, source, summary_json, started_at, finished_at)
             VALUES (:site_id, :scan_id, :mode, :status, :started_by_id, :source, :summary_json, :started_at, :finished_at)'
        );
        $statement->execute([
            'site_id' => $scan['site_id'], 'scan_id' => $scan['scan_id'], 'mode' => $scan['mode'],
            'status' => $scan['status'], 'started_by_id' => $scan['started_by_id'], 'source' => $scan['source'],
            'summary_json' => json_encode($scan['summary'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'started_at' => $scan['started_at'], 'finished_at' => $scan['finished_at'],
        ]);
        $finding = $this->pdo->prepare(
            'INSERT INTO privacy_findings
             (site_id, scan_id, finding_id, kind, service_id, label, category, status, optional_tracking, evidence_json, created_at)
             VALUES (:site_id, :scan_id, :finding_id, :kind, :service_id, :label, :category, :status, :optional_tracking, :evidence_json, UTC_TIMESTAMP(6))'
        );
        foreach ($findings as $item) {
            $finding->execute([
                'site_id' => $scan['site_id'], 'scan_id' => $scan['scan_id'], 'finding_id' => $item['finding_id'],
                'kind' => $item['kind'], 'service_id' => $item['service_id'], 'label' => $item['label'],
                'category' => $item['category'], 'status' => $item['status'],
                'optional_tracking' => $item['optional_tracking'] ? 1 : 0,
                'evidence_json' => json_encode($item['evidence'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        }
    }

    public function appendAudit(
        string $siteId,
        ?string $actorId,
        ?string $deviceId,
        string $action,
        ?string $entityId,
        array $details,
    ): void {
        $statement = $this->pdo->prepare(
            "INSERT INTO audit_events
             (site_id, actor_id, device_id, action, entity_type, entity_id, outcome, details_json, created_at)
             VALUES (:site_id, :actor_id, :device_id, :action, 'privacy', :entity_id, 'allowed', :details_json, UTC_TIMESTAMP(6))"
        );
        $statement->execute([
            'site_id' => $siteId, 'actor_id' => $actorId, 'device_id' => $deviceId,
            'action' => $action, 'entity_id' => $entityId,
            'details_json' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
