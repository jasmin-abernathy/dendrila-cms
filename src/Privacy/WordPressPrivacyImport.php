<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

final class WordPressPrivacyImport
{
    /** @var array<string, string> */
    private const CATEGORY_MAP = [
        'mesure d’audience' => 'statistics',
        'analyse comportementale' => 'statistics',
        'marketing / suivi' => 'marketing',
        'marketing automation / suivi du site' => 'marketing',
        'e-mailing / newsletter' => 'marketing',
        'contenu / cartographie' => 'external_media',
        'contenu embarqué' => 'external_media',
        'police externe / confidentialité' => 'external_media',
        'sécurité / anti-spam' => 'functional',
        'formulaire externe' => 'functional',
        'réservation / rendez-vous' => 'functional',
        'paiement externe' => 'functional',
        'messagerie / contact' => 'functional',
        'adhésions / dons / billetterie' => 'functional',
        'événement / billetterie' => 'functional',
        'gestion de balises' => 'unknown',
    ];

    /**
     * Accepts either canonical keys (settings/legal_profile/scan) or the raw WordPress option names.
     *
     * @param array<string, mixed> $payload
     * @return array{
     *   settings_changes:array<string,mixed>,
     *   findings:list<array<string,mixed>>,
     *   scan_mode:string,
     *   warnings:list<string>,
     *   source_meta:array<string,mixed>
     * }
     */
    public function map(array $payload): array
    {
        $hasSettings = array_key_exists('settings', $payload)
            || array_key_exists('pixel_trackers_manager_settings', $payload);
        $settings = $this->object(
            $payload['settings'] ?? $payload['pixel_trackers_manager_settings'] ?? [],
            'WordPress Dendrila Privacy settings',
        );
        $profile = $this->object(
            $payload['legal_profile'] ?? $payload['pixel_trackers_manager_legal_profile'] ?? [],
            'WordPress Dendrila Privacy legal profile',
        );
        $scan = $this->object(
            $payload['scan'] ?? $payload['pixel_trackers_manager_scan_current'] ?? [],
            'WordPress Dendrila Privacy scan',
        );

        $warnings = [];
        $changes = [];
        if ($hasSettings) {
            $changes['consent'] = [
                'enabled' => $this->boolish($settings['consent_enabled'] ?? false, 'consent_enabled'),
                'layout' => $this->enum($settings['consent_layout'] ?? 'bar', ['bar','card'], 'consent_layout'),
                'retention_days' => $this->integerRange(
                    $settings['consent_retention_days'] ?? 180,
                    30,
                    365,
                    'consent_retention_days',
                ),
                'footer_link' => $this->boolish($settings['consent_footer_link'] ?? true, 'consent_footer_link'),
            ];
            $changes['scan'] = [
                'schedule' => $this->enum($settings['schedule'] ?? 'off', ['off','daily','weekly'], 'schedule'),
            ];
        }

        if ($profile !== []) {
            $supported = array_fill_keys(array_keys(LegalProfile::defaults()), true);
            $filtered = [];
            foreach ($profile as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }
                if (isset($supported[$key])) {
                    $filtered[$key] = $value;
                } else {
                    $warnings[] = "Profil WordPress ignoré : champ inconnu {$key}.";
                }
            }
            if (array_key_exists('wizard_reviewed_once', $filtered) && !is_bool($filtered['wizard_reviewed_once'])) {
                $filtered['wizard_reviewed_once'] = $this->boolish(
                    $filtered['wizard_reviewed_once'],
                    'wizard_reviewed_once',
                );
            }
            $changes['legal_profile'] = LegalProfile::normalize($filtered);
        }

        foreach ([
            'privacy_page_id',
            'legal_notice_page_id',
            'cookie_page_id',
            'auto_sync_page',
            'scan_limit',
            'full_scan_limit',
            'adapter_overrides',
            'adapter_previous',
            'consent_style',
        ] as $wordpressOnly) {
            if (array_key_exists($wordpressOnly, $settings)) {
                $warnings[] = "Réglage WordPress non importé : {$wordpressOnly}.";
            }
        }

        $findings = [];
        $rawFindings = $scan['findings'] ?? [];
        if ($rawFindings !== []) {
            if (!is_array($rawFindings) || !array_is_list($rawFindings)) {
                throw new \InvalidArgumentException('WordPress scan findings must be a list.');
            }
            foreach (array_slice($rawFindings, 0, 1000) as $finding) {
                if (!is_array($finding) || ($finding !== [] && array_is_list($finding))) {
                    throw new \InvalidArgumentException('WordPress scan finding must be an object.');
                }
                $mapped = $this->finding($finding);
                if ($mapped !== null) {
                    $findings[] = $mapped;
                }
            }
            if (count($rawFindings) > 1000) {
                $warnings[] = 'Le scan WordPress contenait plus de 1000 constats ; seuls les 1000 premiers ont été préparés.';
            }
        }

        $scanMode = 'standard';
        $coverage = $scan['coverage'] ?? null;
        if (is_array($coverage) && !array_is_list($coverage)) {
            $mode = $coverage['mode'] ?? null;
            if (is_string($mode) && in_array($mode, ['standard','full'], true)) {
                $scanMode = $mode;
            }
        }

        return [
            'settings_changes' => $changes,
            'findings' => $findings,
            'scan_mode' => $scanMode,
            'warnings' => array_values(array_unique($warnings)),
            'source_meta' => [
                'plugin_version' => is_string($scan['plugin_version'] ?? null) ? $scan['plugin_version'] : null,
                'scan_version' => is_int($scan['version'] ?? null) ? $scan['version'] : null,
                'generated_at' => is_string($scan['generated_at'] ?? null) ? $scan['generated_at'] : null,
            ],
        ];
    }

    /** @param array<string,mixed> $finding @return array<string,mixed>|null */
    private function finding(array $finding): ?array
    {
        $serviceId = $finding['id'] ?? null;
        $label = $finding['label'] ?? null;
        if (!is_string($serviceId) || preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,63}$/', $serviceId) !== 1
            || !is_string($label) || trim($label) === '') {
            return null;
        }

        $serviceId = strtolower($serviceId);
        $rawCategory = is_string($finding['category'] ?? null) ? trim($finding['category']) : '';
        $category = self::CATEGORY_MAP[mb_strtolower($rawCategory)] ?? 'unknown';
        $active = !empty($finding['active_tracking']);
        $blocked = !empty($finding['blocked_by_consent']);
        $observed = !empty($finding['observed_html']);
        $isTracking = !array_key_exists('is_tracking', $finding) || !empty($finding['is_tracking']);

        $evidence = [
            'imported_from' => 'dendrila-privacy-wordpress',
            'wordpress_category' => $rawCategory,
            'tracking_state' => is_string($finding['tracking_state'] ?? null) ? $finding['tracking_state'] : null,
            'tracking_basis' => is_string($finding['tracking_basis'] ?? null) ? $finding['tracking_basis'] : null,
            'confidence' => is_string($finding['confidence'] ?? null) ? $finding['confidence'] : null,
            'source' => is_string($finding['source'] ?? null) ? mb_substr($finding['source'], 0, 1000) : null,
            'urls' => $this->stringList($finding['urls'] ?? [], 50, 2048),
        ];
        if (is_array($finding['evidence'] ?? null)) {
            $evidence['wordpress_evidence'] = array_slice($finding['evidence'], 0, 50);
        }

        return [
            'kind' => $isTracking ? 'tracker' : 'third_party_service',
            'service_id' => $serviceId,
            'label' => mb_substr(trim($label), 0, 190),
            'category' => $category,
            'status' => ($active || $blocked || $observed) ? 'detected' : 'needs_review',
            'optional_tracking' => $serviceId !== 'recaptcha' && ($blocked || ($isTracking && $active)),
            'evidence' => $evidence,
        ];
    }

    /** @return array<string,mixed> */
    private function object(mixed $value, string $label): array
    {
        if ($value === null || $value === []) {
            return [];
        }
        if (!is_array($value) || array_is_list($value)) {
            throw new \InvalidArgumentException("{$label} must be a JSON object.");
        }
        return $value;
    }

    private function boolish(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1 || $value === '0' || $value === '1') {
            return (bool) (int) $value;
        }
        throw new \InvalidArgumentException("{$field} must be boolean-like.");
    }

    /** @param list<string> $allowed */
    private function enum(mixed $value, array $allowed, string $field): string
    {
        if (!is_string($value) || !in_array($value, $allowed, true)) {
            throw new \InvalidArgumentException("Invalid {$field} value.");
        }
        return $value;
    }

    private function integerRange(mixed $value, int $min, int $max, string $field): int
    {
        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $value = (int) $value;
        }
        if (!is_int($value) || $value < $min || $value > $max) {
            throw new \InvalidArgumentException("{$field} must be an integer between {$min} and {$max}.");
        }
        return $value;
    }

    /** @return list<string> */
    private function stringList(mixed $value, int $maxItems, int $maxLength): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }
        $out = [];
        foreach (array_slice($value, 0, $maxItems) as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = trim($item);
            if ($item !== '') {
                $out[] = mb_substr($item, 0, $maxLength);
            }
        }
        return $out;
    }
}
