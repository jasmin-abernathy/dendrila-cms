<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;

final readonly class PrivacyService
{
    public function __construct(
        private PrivacyRepository $repository,
        private AuthorizationService $authorization,
    ) {
    }

    /** @return array<string, mixed> */
    public static function defaultSettings(): array
    {
        return [
            'consent' => [
                'enabled' => false,
                'layout' => 'bar',
                'retention_days' => 180,
                'footer_link' => true,
            ],
            'scan' => ['schedule' => 'off'],
            'legal_profile' => LegalProfile::defaults(),
            'external_practices' => [],
        ];
    }

    /** @return array<string, mixed> */
    public function state(ActorContext $actor): array
    {
        $this->authorization->require($actor, 'privacy.read');
        $settings = $this->normalizedStoredSettings($actor->siteId);
        $scan = $this->repository->findLatestCompletedScan($actor->siteId);
        $findings = $scan === null ? [] : $this->repository->listFindings($actor->siteId, (string) $scan['scan_id']);
        return ['settings' => $settings, 'latest_scan' => $scan, 'findings' => $findings];
    }

    /** @param array<string, mixed> $changes @return array<string, mixed> */
    public function updateSettings(ActorContext $actor, array $changes): array
    {
        $this->authorization->require($actor, 'privacy.manage');
        return $this->repository->transaction(function () use ($actor, $changes): array {
            $current = $this->normalizedStoredSettings($actor->siteId);
            $merged = self::mergeSettings($current, $changes);
            $settings = self::normalizeSettings($merged);
            $this->repository->saveSettings($actor->siteId, $settings);
            $this->repository->appendAudit(
                $actor->siteId,
                $actor->actorId,
                $actor->deviceId,
                'privacy.settings_update',
                null,
                ['source' => $actor->source],
            );
            return $settings;
        });
    }

    /** @return array<string, mixed> */
    public function legalProfile(ActorContext $actor): array
    {
        $this->authorization->require($actor, 'privacy.read');
        return $this->normalizedStoredSettings($actor->siteId)['legal_profile'];
    }

    /** @param array<string, mixed> $changes @return array<string, mixed> */
    public function updateLegalProfile(ActorContext $actor, array $changes): array
    {
        $this->authorization->require($actor, 'privacy.manage');
        return $this->repository->transaction(function () use ($actor, $changes): array {
            $settings = $this->normalizedStoredSettings($actor->siteId);
            $settings['legal_profile'] = LegalProfile::merge($settings['legal_profile'], $changes);
            $settings = self::normalizeSettings($settings);
            $this->repository->saveSettings($actor->siteId, $settings);
            $this->repository->appendAudit(
                $actor->siteId,
                $actor->actorId,
                $actor->deviceId,
                'privacy.legal_profile_update',
                null,
                ['fields' => array_values(array_keys($changes)), 'source' => $actor->source],
            );
            return $settings['legal_profile'];
        });
    }

    /** @return array<string, array{complete:bool,missing:list<string>}> */
    public function legalProgress(ActorContext $actor): array
    {
        return LegalProfile::progress($this->legalProfile($actor));
    }

    /** @return array{kind:string,html:string,readiness:array{errors:list<string>,warnings:list<string>},source_hash:string} */
    public function legalDocumentPreview(ActorContext $actor, string $kind): array
    {
        $this->authorization->require($actor, 'privacy.read');
        $settings = $this->normalizedStoredSettings($actor->siteId);
        $manifest = $this->consentManifest($actor->siteId);
        $renderer = new LegalDocumentRenderer();
        $profile = $settings['legal_profile'];

        return [
            'kind' => $kind,
            'html' => $renderer->render($kind, $profile, $manifest),
            'readiness' => $renderer->readiness($kind, $profile, $manifest),
            'source_hash' => self::legalDocumentSourceHash($kind, $profile, $manifest),
        ];
    }

    /** @return array<string,mixed> */
    public function approveLegalDocument(ActorContext $actor, string $kind): array
    {
        $this->authorization->require($actor, 'privacy.manage');
        $preview = $this->legalDocumentPreview($actor, $kind);
        if ($preview['readiness']['errors'] !== []) {
            throw new \InvalidArgumentException(
                'Legal document is incomplete: ' . implode('; ', $preview['readiness']['errors'])
            );
        }
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $document = [
            'kind' => $kind,
            'html' => $preview['html'],
            'source_hash' => $preview['source_hash'],
            'approved_by_id' => $actor->actorId,
            'approved_at' => $now,
        ];

        return $this->repository->transaction(function () use ($actor, $document): array {
            $this->repository->saveLegalDocument($actor->siteId, $document);
            $this->repository->appendAudit(
                $actor->siteId,
                $actor->actorId,
                $actor->deviceId,
                'privacy.legal_document_approve',
                $document['kind'],
                ['kind' => $document['kind'], 'source' => $actor->source],
            );
            return $document;
        });
    }

    /** @return array{kind:string,html:string,approved_at:string}|null */
    public function publicLegalDocument(string $siteId, string $kind): ?array
    {
        if ($siteId === '') {
            throw new \InvalidArgumentException('A site id is required.');
        }
        if (!in_array($kind, LegalDocumentRenderer::KINDS, true)) {
            throw new \InvalidArgumentException('Unknown privacy legal document kind.');
        }
        $stored = $this->repository->findLegalDocument($siteId, $kind);
        if ($stored === null) {
            return null;
        }
        $settings = $this->normalizedStoredSettings($siteId);
        $manifest = $this->consentManifest($siteId);
        $expected = self::legalDocumentSourceHash($kind, $settings['legal_profile'], $manifest);
        if (!is_string($stored['source_hash'] ?? null) || !hash_equals($expected, $stored['source_hash'])) {
            return null;
        }
        return [
            'kind' => $kind,
            'html' => (string) $stored['html'],
            'approved_at' => (string) $stored['approved_at'],
        ];
    }

    /**
     * Stores a completed inspection. The actual collector is adapter-specific; PrivacyInspector is the built-in detector.
     * @param list<array<string, mixed>> $findings
     * @return array<string, mixed>
     */
    public function recordCompletedScan(ActorContext $actor, string $mode, array $findings): array
    {
        $this->authorization->require($actor, 'privacy.scan');
        if (!in_array($mode, ['standard', 'full'], true)) {
            throw new \InvalidArgumentException('Unknown privacy scan mode.');
        }
        if (count($findings) > 1000) {
            throw new \InvalidArgumentException('A privacy scan is limited to 1000 findings.');
        }
        $normalized = array_map([self::class, 'normalizeFinding'], $findings);
        $scanId = bin2hex(random_bytes(16));
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
        $summary = [
            'total' => count($normalized),
            'detected' => count(array_filter($normalized, static fn (array $item): bool => $item['status'] === 'detected')),
            'needs_review' => count(array_filter($normalized, static fn (array $item): bool => $item['status'] === 'needs_review')),
            'optional_tracking' => count(array_filter($normalized, static fn (array $item): bool => $item['optional_tracking'] === true)),
        ];
        $scan = [
            'site_id' => $actor->siteId,
            'scan_id' => $scanId,
            'mode' => $mode,
            'status' => 'completed',
            'started_by_id' => $actor->actorId,
            'source' => $actor->source,
            'summary' => $summary,
            'started_at' => $now,
            'finished_at' => $now,
        ];
        return $this->repository->transaction(function () use ($actor, $scan, $normalized): array {
            $this->repository->saveCompletedScan($scan, $normalized);
            $this->repository->appendAudit(
                $actor->siteId,
                $actor->actorId,
                $actor->deviceId,
                'privacy.scan_complete',
                (string) $scan['scan_id'],
                ['mode' => $scan['mode'], 'summary' => $scan['summary'], 'source' => $actor->source],
            );
            return ['scan' => $scan, 'findings' => $normalized];
        });
    }

    /**
     * Safe public/front-adapter configuration. Legal profile and audit evidence are intentionally excluded.
     * @return array<string, mixed>
     */
    public function consentManifest(string $siteId): array
    {
        if ($siteId === '') {
            throw new \InvalidArgumentException('A site id is required.');
        }
        $settings = $this->normalizedStoredSettings($siteId);
        $scan = $this->repository->findLatestCompletedScan($siteId);
        $findings = $scan === null ? [] : $this->repository->listFindings($siteId, (string) $scan['scan_id']);
        $services = [];
        foreach ($findings as $finding) {
            if (($finding['optional_tracking'] ?? false) !== true || !is_string($finding['service_id'] ?? null)) {
                continue;
            }
            $services[] = [
                'id' => $finding['service_id'],
                'label' => (string) ($finding['label'] ?? $finding['service_id']),
                'category' => (string) ($finding['category'] ?? 'unknown'),
            ];
        }
        usort($services, static fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
        $consent = $settings['consent'];
        $fingerprint = hash('sha256', json_encode([$consent, $services], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        return [
            'enabled' => $consent['enabled'],
            'layout' => $consent['layout'],
            'retention_days' => $consent['retention_days'],
            'footer_link' => $consent['footer_link'],
            'services' => $services,
            'fingerprint' => $fingerprint,
        ];
    }

    /** @param array<string,mixed> $profile @param array<string,mixed> $manifest */
    private static function legalDocumentSourceHash(string $kind, array $profile, array $manifest): string
    {
        if (!in_array($kind, LegalDocumentRenderer::KINDS, true)) {
            throw new \InvalidArgumentException('Unknown privacy legal document kind.');
        }
        $source = $kind === 'legal_notice'
            ? [$kind, $profile]
            : [$kind, $profile, [
                'enabled' => $manifest['enabled'] ?? false,
                'retention_days' => $manifest['retention_days'] ?? 180,
                'services' => $manifest['services'] ?? [],
                'fingerprint' => $manifest['fingerprint'] ?? '',
            ]];
        return hash(
            'sha256',
            json_encode($source, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    private static function normalizeSettings(array $settings): array
    {
        $consent = $settings['consent'] ?? null;
        $scan = $settings['scan'] ?? null;
        $profile = $settings['legal_profile'] ?? null;
        $external = $settings['external_practices'] ?? null;
        if (!is_array($consent) || array_is_list($consent)
            || !is_bool($consent['enabled'] ?? null)
            || !is_string($consent['layout'] ?? null) || !in_array($consent['layout'], ['bar', 'card'], true)
            || !is_int($consent['retention_days'] ?? null) || $consent['retention_days'] < 30 || $consent['retention_days'] > 365
            || !is_bool($consent['footer_link'] ?? null)) {
            throw new \InvalidArgumentException('Invalid privacy consent settings.');
        }
        if (!is_array($scan) || array_is_list($scan) || !is_string($scan['schedule'] ?? null)
            || !in_array($scan['schedule'], ['off', 'daily', 'weekly'], true)) {
            throw new \InvalidArgumentException('Invalid privacy scan settings.');
        }
        if (!is_array($profile) || ($profile !== [] && array_is_list($profile))) {
            throw new \InvalidArgumentException('The legal profile must be an object.');
        }
        $profile = LegalProfile::normalize($profile);
        if (!is_array($external) || !array_is_list($external) || count($external) > 100) {
            throw new \InvalidArgumentException('External practices must be a bounded list.');
        }
        self::assertJsonSize($profile, 65_536, 'legal profile');
        self::assertJsonSize($external, 131_072, 'external practices');
        return [
            'consent' => [
                'enabled' => $consent['enabled'],
                'layout' => $consent['layout'],
                'retention_days' => $consent['retention_days'],
                'footer_link' => $consent['footer_link'],
            ],
            'scan' => ['schedule' => $scan['schedule']],
            'legal_profile' => $profile,
            'external_practices' => $external,
        ];
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $changes @return array<string, mixed> */
    private static function mergeSettings(array $current, array $changes): array
    {
        foreach ($changes as $key => $value) {
            if (!in_array($key, ['consent', 'scan', 'legal_profile', 'external_practices'], true)) {
                throw new \InvalidArgumentException("Unknown privacy setting '{$key}'.");
            }
            if (in_array($key, ['consent', 'scan'], true) && is_array($value) && !array_is_list($value)) {
                $current[$key] = array_replace($current[$key], $value);
            } else {
                $current[$key] = $value;
            }
        }
        return $current;
    }

    /** @param array<string, mixed> $finding @return array<string, mixed> */
    private static function normalizeFinding(array $finding): array
    {
        $kinds = ['tracker', 'third_party_service', 'legal_gap', 'consent_issue', 'external_practice'];
        $statuses = ['detected', 'needs_review', 'documented', 'resolved'];
        $categories = ['statistics', 'marketing', 'external_media', 'functional', 'unknown'];
        if (!is_string($finding['kind'] ?? null) || !in_array($finding['kind'], $kinds, true)
            || !is_string($finding['label'] ?? null) || trim($finding['label']) === '' || strlen($finding['label']) > 190
            || !is_string($finding['status'] ?? null) || !in_array($finding['status'], $statuses, true)
            || !is_bool($finding['optional_tracking'] ?? null)) {
            throw new \InvalidArgumentException('Invalid privacy finding.');
        }
        $serviceId = $finding['service_id'] ?? null;
        if ($serviceId !== null && (!is_string($serviceId) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $serviceId))) {
            throw new \InvalidArgumentException('Invalid privacy service id.');
        }
        $category = $finding['category'] ?? 'unknown';
        if (!is_string($category) || !in_array($category, $categories, true)) {
            throw new \InvalidArgumentException('Invalid privacy finding category.');
        }
        $evidence = $finding['evidence'] ?? [];
        if (!is_array($evidence)) {
            throw new \InvalidArgumentException('Privacy evidence must be JSON-compatible data.');
        }
        self::assertJsonSize($evidence, 65_536, 'privacy evidence');
        return [
            'finding_id' => bin2hex(random_bytes(16)),
            'kind' => $finding['kind'],
            'service_id' => $serviceId,
            'label' => trim($finding['label']),
            'category' => $category,
            'status' => $finding['status'],
            'optional_tracking' => $finding['optional_tracking'],
            'evidence' => $evidence,
        ];
    }

    /** @return array<string, mixed> */
    private function normalizedStoredSettings(string $siteId): array
    {
        return self::normalizeSettings($this->repository->findSettings($siteId) ?? self::defaultSettings());
    }

    private static function assertJsonSize(mixed $value, int $maxBytes, string $label): void
    {
        try {
            $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $error) {
            throw new \InvalidArgumentException("Invalid {$label} JSON.", previous: $error);
        }
        if (strlen($json) > $maxBytes) {
            throw new \InvalidArgumentException(ucfirst($label) . ' is too large.');
        }
    }
}
