<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

final class PrivacySettingsModule
{
    public const ID = 'privacy';

    /** @return array<string, mixed> */
    public static function definition(bool $writable): array
    {
        return [
            'id' => self::ID,
            'kind' => 'settings',
            'title' => 'Vie privée',
            'subtitle' => 'Consentement et analyses techniques',
            'icon' => 'shield',
            'order' => 900,
            'writable' => $writable,
            'fields' => [
                [
                    'id' => 'consent_enabled',
                    'type' => 'boolean',
                    'label' => 'Gestion du consentement',
                    'hint' => 'Active la barre ou l’encart natif lorsque des services facultatifs sont détectés.',
                ],
                [
                    'id' => 'consent_layout',
                    'type' => 'single_choice',
                    'label' => 'Présentation',
                    'choices' => [
                        ['value' => 'bar', 'label' => 'Barre'],
                        ['value' => 'card', 'label' => 'Encart'],
                    ],
                ],
                [
                    'id' => 'consent_retention_days',
                    'type' => 'number',
                    'label' => 'Durée de mémorisation du choix',
                    'hint' => 'En jours, entre 30 et 365.',
                    'min' => 30,
                    'max' => 365,
                ],
                [
                    'id' => 'consent_footer_link',
                    'type' => 'boolean',
                    'label' => 'Afficher « Gérer mes choix »',
                ],
                [
                    'id' => 'scan_schedule',
                    'type' => 'single_choice',
                    'label' => 'Analyse planifiée',
                    'choices' => [
                        ['value' => 'off', 'label' => 'Désactivée'],
                        ['value' => 'daily', 'label' => 'Quotidienne'],
                        ['value' => 'weekly', 'label' => 'Hebdomadaire'],
                    ],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $settings @return array<string, mixed> */
    public static function values(array $settings): array
    {
        $consent = $settings['consent'] ?? null;
        $scan = $settings['scan'] ?? null;
        if (!is_array($consent) || !is_array($scan)) {
            throw new \RuntimeException('Stored privacy settings are incomplete.');
        }

        return [
            'consent_enabled' => (bool) ($consent['enabled'] ?? false),
            'consent_layout' => (string) ($consent['layout'] ?? 'bar'),
            'consent_retention_days' => (int) ($consent['retention_days'] ?? 180),
            'consent_footer_link' => (bool) ($consent['footer_link'] ?? true),
            'scan_schedule' => (string) ($scan['schedule'] ?? 'off'),
        ];
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public static function changes(array $payload): array
    {
        if ($payload === []) {
            throw new \InvalidArgumentException('The privacy change contains no fields.');
        }

        $allowed = [
            'consent_enabled',
            'consent_layout',
            'consent_retention_days',
            'consent_footer_link',
            'scan_schedule',
        ];
        foreach (array_keys($payload) as $field) {
            if (!is_string($field) || !in_array($field, $allowed, true)) {
                throw new \InvalidArgumentException("Field '{$field}' is not writable in the privacy module.");
            }
        }

        $changes = [];
        if (array_key_exists('consent_enabled', $payload)) {
            if (!is_bool($payload['consent_enabled'])) {
                throw new \InvalidArgumentException('consent_enabled must be a boolean.');
            }
            $changes['consent']['enabled'] = $payload['consent_enabled'];
        }
        if (array_key_exists('consent_layout', $payload)) {
            if (!is_string($payload['consent_layout'])
                || !in_array($payload['consent_layout'], ['bar', 'card'], true)) {
                throw new \InvalidArgumentException('consent_layout must be bar or card.');
            }
            $changes['consent']['layout'] = $payload['consent_layout'];
        }
        if (array_key_exists('consent_retention_days', $payload)) {
            if (!is_int($payload['consent_retention_days'])
                || $payload['consent_retention_days'] < 30
                || $payload['consent_retention_days'] > 365) {
                throw new \InvalidArgumentException('consent_retention_days must be an integer between 30 and 365.');
            }
            $changes['consent']['retention_days'] = $payload['consent_retention_days'];
        }
        if (array_key_exists('consent_footer_link', $payload)) {
            if (!is_bool($payload['consent_footer_link'])) {
                throw new \InvalidArgumentException('consent_footer_link must be a boolean.');
            }
            $changes['consent']['footer_link'] = $payload['consent_footer_link'];
        }
        if (array_key_exists('scan_schedule', $payload)) {
            if (!is_string($payload['scan_schedule'])
                || !in_array($payload['scan_schedule'], ['off', 'daily', 'weekly'], true)) {
                throw new \InvalidArgumentException('scan_schedule must be off, daily or weekly.');
            }
            $changes['scan']['schedule'] = $payload['scan_schedule'];
        }

        return $changes;
    }

    /** @param list<array<string, mixed>> $modules */
    public static function assertReservedIdAvailable(array $modules): void
    {
        foreach ($modules as $module) {
            if (is_array($module) && ($module['id'] ?? null) === self::ID) {
                throw new \RuntimeException("Module id '" . self::ID . "' is reserved by Dendrila Privacy.");
            }
        }
    }
}
