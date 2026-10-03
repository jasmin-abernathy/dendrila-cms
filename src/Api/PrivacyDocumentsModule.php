<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

final class PrivacyDocumentsModule
{
    public const ID = 'privacy_documents';

    /** @return array<string,mixed> */
    public static function definition(bool $writable): array
    {
        $definition = [
            'id' => self::ID,
            'kind' => 'records',
            'title' => 'Vie privée — documents publics',
            'subtitle' => 'Prévisualiser l’état des mentions légales, de la politique de confidentialité et des cookies',
            'icon' => 'file-check',
            'order' => 940,
            'writable' => $writable,
            'fields' => [
                [
                    'id' => 'readiness',
                    'type' => 'text',
                    'label' => 'État du contenu',
                    'hint' => 'Les documents incomplets ne peuvent pas être validés.',
                ],
                [
                    'id' => 'approved_at',
                    'type' => 'text',
                    'label' => 'Dernière validation',
                ],
            ],
        ];
        if ($writable) {
            $definition['actions'] = [[
                'id' => 'approve',
                'label' => 'Valider / republier',
                'tone' => 'primary',
                'requires_confirmation' => true,
                'confirmation_text' => 'Valider cette version pour publication ? Toute modification ultérieure des données sources demandera une nouvelle validation.',
                'allow_offline' => false,
            ]];
        }
        return $definition;
    }

    /**
     * @param list<array<string,mixed>> $states
     * @return array{items:list<array<string,mixed>>}
     */
    public static function snapshot(array $states): array
    {
        $items = [];
        foreach ($states as $state) {
            $kind = $state['kind'] ?? null;
            if (!is_string($kind) || !in_array($kind, ['legal_notice','privacy','cookies'], true)) {
                continue;
            }
            $errors = is_array($state['readiness']['errors'] ?? null) ? $state['readiness']['errors'] : [];
            $warnings = is_array($state['readiness']['warnings'] ?? null) ? $state['readiness']['warnings'] : [];
            $current = ($state['current'] ?? false) === true;
            $hasApproved = ($state['has_approved_version'] ?? false) === true;

            $status = match (true) {
                $errors !== [] => 'À compléter',
                $current => 'Publié',
                $hasApproved => 'À revalider',
                default => 'Prêt à valider',
            };
            $readiness = match (true) {
                $errors !== [] => implode(' · ', array_slice(array_map('strval', $errors), 0, 4)),
                $warnings !== [] => 'Prêt, avec point(s) à vérifier : ' . implode(' · ', array_slice(array_map('strval', $warnings), 0, 3)),
                $current => 'Version validée à jour',
                $hasApproved => 'Les données ont changé depuis la dernière validation',
                default => 'Aucun blocage détecté',
            };
            $items[] = [
                'id' => $kind,
                'title' => self::label($kind),
                'subtitle' => $current ? 'Version publique à jour' : $status,
                'status' => $status,
                'readiness' => mb_substr($readiness, 0, 1000),
                'approved_at' => is_string($state['approved_at'] ?? null) ? $state['approved_at'] : '',
            ];
        }
        return ['items' => $items];
    }

    public static function label(string $kind): string
    {
        return match ($kind) {
            'legal_notice' => 'Mentions légales',
            'privacy' => 'Politique de confidentialité',
            'cookies' => 'Politique cookies et traceurs',
            default => throw new \InvalidArgumentException('Unknown privacy legal document kind.'),
        };
    }

    /** @param list<array<string,mixed>> $modules */
    public static function assertReservedIdAvailable(array $modules): void
    {
        foreach ($modules as $module) {
            if (is_array($module) && ($module['id'] ?? null) === self::ID) {
                throw new \RuntimeException("Module id '" . self::ID . "' is reserved by Dendrila Privacy.");
            }
        }
    }
}
