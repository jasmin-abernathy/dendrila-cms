<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Privacy\LegalProfile;

final class PrivacyLegalModules
{
    public const LEGAL_ID = 'privacy_legal';
    public const TREATMENTS_ID = 'privacy_treatments';
    public const SOURCES_ID = 'privacy_sources';

    /** @return list<string> */
    public static function ids(): array
    {
        return [self::LEGAL_ID, self::TREATMENTS_ID, self::SOURCES_ID];
    }

    public static function handles(string $moduleId): bool
    {
        return in_array($moduleId, self::ids(), true);
    }

    /** @return list<array<string, mixed>> */
    public static function definitions(bool $writable): array
    {
        return [
            [
                'id' => self::LEGAL_ID,
                'kind' => 'settings',
                'title' => 'Vie privée — documentation',
                'subtitle' => 'Identité, flux, risques, cookies, e-mails et outils externes',
                'icon' => 'file-text',
                'order' => 910,
                'writable' => $writable,
                'fields' => self::legalFields(),
            ],
            [
                'id' => self::TREATMENTS_ID,
                'kind' => 'records',
                'title' => 'Vie privée — traitements',
                'subtitle' => 'Finalités, données, base légale, destinataires et conservation',
                'icon' => 'list',
                'order' => 920,
                'writable' => $writable,
                'options' => [
                    'allow_create' => $writable,
                    'allow_update' => $writable,
                    'allow_delete' => $writable,
                ],
                'fields' => self::treatmentFields(),
            ],
            [
                'id' => self::SOURCES_ID,
                'kind' => 'records',
                'title' => 'Vie privée — sources indirectes',
                'subtitle' => 'Services qui transmettent ou traitent des données hors du site',
                'icon' => 'share',
                'order' => 930,
                'writable' => $writable,
                'options' => [
                    'allow_create' => $writable,
                    'allow_update' => $writable,
                    'allow_delete' => $writable,
                ],
                'fields' => self::sourceFields(),
            ],
        ];
    }

    /** @param array<string, mixed> $profile @return array<string, mixed> */
    public static function snapshot(array $profile): array
    {
        $profile = LegalProfile::normalize($profile);
        return [
            self::LEGAL_ID => self::legalValues($profile),
            self::TREATMENTS_ID => [
                'items' => array_map(self::treatmentItem(...), $profile['treatments']),
            ],
            self::SOURCES_ID => [
                'items' => array_map(self::sourceItem(...), $profile['indirect_services']),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @param array<string, mixed> $payload
     * @return array{changes:array<string,mixed>,result:array<string,mixed>}
     */
    public static function apply(array $profile, string $moduleId, string $action, array $payload): array
    {
        $profile = LegalProfile::normalize($profile);

        if ($moduleId === self::LEGAL_ID) {
            if ($action !== 'update_fields') {
                throw new \InvalidArgumentException('The legal privacy module only supports update_fields.');
            }
            $changes = self::legalChanges($payload);
            $updated = LegalProfile::merge($profile, $changes);
            return [
                'changes' => $changes,
                'result' => ['values' => self::legalValues($updated)],
            ];
        }

        if ($moduleId === self::TREATMENTS_ID) {
            return self::applyRecord(
                $profile,
                $action,
                $payload,
                'treatments',
                self::treatmentPayload(...),
                self::treatmentItem(...),
                8,
            );
        }

        if ($moduleId === self::SOURCES_ID) {
            return self::applyRecord(
                $profile,
                $action,
                $payload,
                'indirect_services',
                self::sourcePayload(...),
                self::sourceItem(...),
                20,
            );
        }

        throw new \InvalidArgumentException('Unknown native privacy module.');
    }

    /** @param list<array<string, mixed>> $modules */
    public static function assertReservedIdsAvailable(array $modules): void
    {
        $reserved = array_fill_keys(self::ids(), true);
        foreach ($modules as $module) {
            $id = is_array($module) ? ($module['id'] ?? null) : null;
            if (is_string($id) && isset($reserved[$id])) {
                throw new \RuntimeException("Module id '{$id}' is reserved by Dendrila Privacy.");
            }
        }
    }

    /** @return list<array<string,mixed>> */
    private static function legalFields(): array
    {
        return [
            self::textField('controller_name', 'Responsable du traitement'),
            self::multilineField('controller_address', 'Adresse du responsable'),
            self::textField('privacy_contact', 'Contact vie privée'),
            self::choiceField('dpo_designated', 'DPO désigné', self::yesNoUnknown()),
            self::textField('dpo_contact', 'Coordonnées du DPO'),
            self::textField('site_editor_name', 'Éditeur du site'),
            self::multilineField('site_editor_address', 'Adresse de l’éditeur'),
            self::choiceField('entity_type', 'Type de structure', [
                'unknown' => 'Je ne sais pas encore',
                'company' => 'Entreprise',
                'individual' => 'Indépendant / personne physique',
                'association' => 'Association',
                'other' => 'Autre',
            ]),
            self::textField('company_country', 'Pays de la structure', 2),
            self::textField('company_registration_number', 'Numéro d’immatriculation'),
            self::textField('company_registration_label', 'Libellé du registre'),
            self::textField('company_siren', 'SIREN'),
            self::textField('company_siret', 'SIRET'),
            self::textField('company_vat', 'Numéro de TVA'),
            self::textField('company_legal_form', 'Forme juridique'),
            self::textField('company_capital', 'Capital'),
            self::textField('company_registry', 'Registre'),
            self::textField('company_activity', 'Activité'),
            self::textField('site_name', 'Nom du site'),
            self::urlField('site_url', 'Adresse du site'),
            self::emailField('public_contact_email', 'E-mail public'),
            self::textField('public_contact_phone', 'Téléphone public'),
            self::textField('publication_director', 'Direction de la publication'),
            self::textField('host_name', 'Hébergeur'),
            self::multilineField('host_address', 'Adresse de l’hébergeur'),
            self::textField('host_phone', 'Téléphone de l’hébergeur'),
            self::urlField('host_source_url', 'Source des informations hébergeur'),
            self::multilineField('regulated_activity_details', 'Activité réglementée'),
            self::textField('rep_idu', 'Identifiant REP / IDU'),
            self::choiceField('representative_applicable', 'Représentant applicable', self::yesNoUnknown()),
            self::textField('controller_representative', 'Représentant du responsable'),
            self::textField('controller_representative_contact', 'Contact du représentant'),
            self::choiceField('collection_mode', 'Mode de collecte', [
                'unknown' => 'Je ne sais pas encore',
                'direct' => 'Directe',
                'indirect' => 'Indirecte',
                'both' => 'Directe et indirecte',
            ]),
            self::multilineField('indirect_categories', 'Catégories de données reçues indirectement'),
            self::multilineField('indirect_sources', 'Autres sources indirectes'),
            self::multilineField('mandatory_information', 'Caractère obligatoire des informations'),
            self::choiceField('transfers_status', 'Transferts hors UE/EEE', self::yesNoUnknown()),
            self::multilineField('transfer_destinations', 'Destinations des transferts'),
            self::multilineField('transfer_mechanism', 'Mécanisme de transfert'),
            self::multilineField('transfer_safeguards', 'Garanties de transfert'),
            self::choiceField('automated_decision', 'Décision automatisée', self::yesNoUnknown()),
            self::multilineField('automated_details', 'Détails de la décision automatisée'),
            self::choiceField('special_categories', 'Données sensibles', self::yesNoUnknown()),
            self::multilineField('special_categories_basis', 'Cadre des données sensibles'),
            self::choiceField('criminal_data', 'Données pénales', self::yesNoUnknown()),
            self::multilineField('criminal_data_basis', 'Cadre des données pénales'),
            self::choiceField('minors_data', 'Données de mineurs', self::yesNoUnknown()),
            self::multilineField('minors_details', 'Modalités prévues pour les mineurs'),
            self::choiceField('high_risk_processing', 'Traitement à risque élevé', self::yesNoUnknown()),
            self::choiceField('dpia_status', 'Analyse d’impact', [
                'unknown' => 'Je ne sais pas encore',
                'not_required' => 'Non requise',
                'to_do' => 'À réaliser',
                'done' => 'Réalisée',
            ]),
            self::choiceField('cookie_nonessential', 'Traceurs facultatifs', self::yesNoUnknown()),
            self::choiceField('cookie_consent_status', 'Choix avant traceurs', [
                'unknown' => 'Je ne sais pas encore',
                'no' => 'Non',
                'yes' => 'Oui',
                'exempt_only' => 'Uniquement des traceurs exemptés',
            ]),
            self::multilineField('cookie_preferences', 'Gestion des préférences cookies'),
            self::textField('cookie_choice_retention', 'Durée de conservation du choix'),
            self::choiceField('cookie_cross_device', 'Choix partagé entre appareils', self::yesNoUnknown()),
            self::multilineField('cookie_cross_device_explanation', 'Explication du partage du choix'),
            self::choiceField('email_pixels', 'Pixels de suivi dans les e-mails', self::yesNoUnknown()),
            self::multilineField('email_pixel_purposes', 'Finalités des pixels e-mail'),
            self::choiceField('email_pixel_status', 'Cadre des pixels e-mail', [
                'unknown' => 'Je ne sais pas encore',
                'consent' => 'Consentement',
                'exempt_delivery' => 'Strictement nécessaire à la délivrance',
            ]),
            self::multilineField('email_pixel_preferences', 'Préférences de suivi e-mail'),
            self::choiceField('tracked_links', 'Liens suivis individuellement', self::yesNoUnknown()),
            self::multilineField('tracked_links_details', 'Détails du suivi de liens'),
            self::choiceField('external_email_use', 'E-mail externe', self::yesNoUnknown()),
            self::choiceField('external_bulk_email', 'Envoi d’e-mails en nombre', self::yesNoUnknown()),
            self::choiceField('external_bulk_unsubscribe', 'Désinscription gérée', self::yesNoUnknown()),
            self::choiceField('external_bulk_bcc', 'Envois en copie cachée', self::yesNoUnknown()),
            self::multilineField('external_email_tools', 'Outils e-mail externes'),
            self::choiceField('external_messaging', 'Messagerie externe', self::yesNoUnknown()),
            self::multilineField('external_messaging_tools', 'Outils de messagerie'),
            self::choiceField('external_booking', 'Réservation externe', self::yesNoUnknown()),
            self::multilineField('external_booking_tools', 'Outils de réservation'),
            self::choiceField('external_forms', 'Formulaires externes', self::yesNoUnknown()),
            self::multilineField('external_form_tools', 'Outils de formulaires'),
            self::choiceField('external_payments', 'Paiements externes', self::yesNoUnknown()),
            self::multilineField('external_payment_tools', 'Outils de paiement'),
            self::choiceField('external_files', 'Fichiers externes / cloud', self::yesNoUnknown()),
            self::multilineField('external_file_tools', 'Outils de fichiers'),
            self::choiceField('backup_reviewed', 'Sauvegardes vérifiées', self::yesNoUnknown()),
            self::textField('supervisory_authority', 'Autorité de contrôle'),
            self::urlField('supervisory_url', 'Lien vers l’autorité de contrôle'),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function treatmentFields(): array
    {
        return [
            self::textField('purpose', 'Finalité'),
            self::multilineField('data_categories', 'Catégories de données'),
            self::choiceField('legal_basis', 'Base légale', [
                'unknown' => 'Je ne sais pas encore',
                'consent' => 'Consentement',
                'contract' => 'Contrat',
                'legal_obligation' => 'Obligation légale',
                'vital_interests' => 'Intérêts vitaux',
                'public_task' => 'Mission d’intérêt public',
                'legitimate_interest' => 'Intérêt légitime',
            ]),
            self::multilineField('basis_detail', 'Précision sur la base légale'),
            self::multilineField('recipients', 'Destinataires'),
            self::multilineField('retention', 'Durée de conservation'),
            self::choiceField('mandatory', 'Caractère obligatoire', [
                'unknown' => 'Je ne sais pas encore',
                'optional' => 'Facultatif',
                'contract' => 'Nécessaire au contrat',
                'law' => 'Imposé par la loi',
            ]),
            self::multilineField('consequences', 'Conséquences en cas de non-fourniture'),
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function sourceFields(): array
    {
        return [
            self::textField('service_id', 'Identifiant du service'),
            self::textField('label', 'Service'),
            self::choiceField('state', 'État', [
                'manual' => 'Déclaré manuellement',
                'active' => 'Actif',
                'potential' => 'Potentiel',
            ]),
            self::multilineField('categories', 'Catégories de données'),
            self::multilineField('source', 'Provenance'),
            self::multilineField('recipients', 'Destinataires'),
            self::choiceField('transfer_status', 'Transfert hors UE/EEE', self::yesNoUnknown()),
            self::multilineField('transfer_destinations', 'Destinations'),
            self::multilineField('transfer_mechanism', 'Mécanisme'),
            self::multilineField('transfer_safeguards', 'Garanties'),
            self::choiceField('automated_decision', 'Décision automatisée', self::yesNoUnknown()),
            self::multilineField('automated_details', 'Détails de la décision automatisée'),
        ];
    }

    /** @param array<string,mixed> $profile @return array<string,mixed> */
    private static function legalValues(array $profile): array
    {
        $values = [];
        foreach (self::legalFields() as $field) {
            $id = $field['id'];
            if (array_key_exists($id, $profile)) {
                $values[$id] = $profile[$id];
            }
        }
        return $values;
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private static function legalChanges(array $payload): array
    {
        $fields = array_fill_keys(array_column(self::legalFields(), 'id'), true);
        if ($payload === []) {
            throw new \InvalidArgumentException('The legal privacy change contains no fields.');
        }
        $changes = [];
        foreach ($payload as $id => $value) {
            if (!is_string($id) || !isset($fields[$id])) {
                throw new \InvalidArgumentException("Field '{$id}' is not writable in the legal privacy module.");
            }
            $changes[$id] = $value;
        }
        LegalProfile::merge(LegalProfile::defaults(), $changes);
        return $changes;
    }

    /**
     * @param array<string,mixed> $profile
     * @param array<string,mixed> $payload
     * @param callable(array<string,mixed>,?string): array<string,mixed> $normalizer
     * @param callable(array<string,mixed>): array<string,mixed> $projector
     * @return array{changes:array<string,mixed>,result:array<string,mixed>}
     */
    private static function applyRecord(
        array $profile,
        string $action,
        array $payload,
        string $key,
        callable $normalizer,
        callable $projector,
        int $maxItems,
    ): array {
        $rows = $profile[$key];
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \RuntimeException("Stored {$key} are invalid.");
        }

        if ($action === 'create_item') {
            if (count($rows) >= $maxItems) {
                throw new \InvalidArgumentException("{$key} reached its maximum size.");
            }
            if (array_key_exists('item_id', $payload)) {
                throw new \InvalidArgumentException('create_item must not contain item_id.');
            }
            $id = bin2hex(random_bytes(16));
            $row = $normalizer($payload, $id);
            $rows[] = $row;
            return [
                'changes' => [$key => $rows],
                'result' => ['item' => $projector($row)],
            ];
        }

        $itemId = $payload['item_id'] ?? null;
        if (!is_string($itemId) || preg_match('/^[a-f0-9]{32}$/', $itemId) !== 1) {
            throw new \InvalidArgumentException('item_id is invalid.');
        }
        $index = null;
        foreach ($rows as $i => $row) {
            if (($row['id'] ?? null) === $itemId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            throw new \InvalidArgumentException('Privacy record does not exist.');
        }

        if ($action === 'update_item') {
            $changes = $payload;
            unset($changes['item_id']);
            if ($changes === []) {
                throw new \InvalidArgumentException('update_item contains no fields.');
            }
            $row = $normalizer(array_replace($rows[$index], $changes), $itemId);
            $rows[$index] = $row;
            return [
                'changes' => [$key => array_values($rows)],
                'result' => ['item' => $projector($row)],
            ];
        }

        if ($action === 'delete_item') {
            if (array_diff(array_keys($payload), ['item_id']) !== []) {
                throw new \InvalidArgumentException('delete_item only accepts item_id.');
            }
            $deleted = $projector($rows[$index]);
            array_splice($rows, $index, 1);
            return [
                'changes' => [$key => array_values($rows)],
                'result' => ['deleted_item' => $deleted],
            ];
        }

        throw new \InvalidArgumentException('Privacy records support create_item, update_item and delete_item.');
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private static function treatmentPayload(array $payload, ?string $id): array
    {
        $allowed = array_fill_keys(array_column(self::treatmentFields(), 'id'), true);
        unset($payload['id']);
        foreach (array_keys($payload) as $key) {
            if (!is_string($key) || !isset($allowed[$key])) {
                throw new \InvalidArgumentException("Unknown treatment field '{$key}'.");
            }
        }
        $candidate = $payload;
        $candidate['id'] = $id ?? bin2hex(random_bytes(16));
        $normalized = LegalProfile::normalize(['treatments' => [$candidate]]);
        return $normalized['treatments'][0];
    }

    /** @param array<string,mixed> $payload @return array<string,mixed> */
    private static function sourcePayload(array $payload, ?string $id): array
    {
        $allowed = array_fill_keys(array_column(self::sourceFields(), 'id'), true);
        unset($payload['id']);
        foreach (array_keys($payload) as $key) {
            if (!is_string($key) || !isset($allowed[$key])) {
                throw new \InvalidArgumentException("Unknown indirect service field '{$key}'.");
            }
        }
        $candidate = $payload;
        $candidate['id'] = $id ?? bin2hex(random_bytes(16));
        $normalized = LegalProfile::normalize(['indirect_services' => [$candidate]]);
        return $normalized['indirect_services'][0];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function treatmentItem(array $row): array
    {
        $item = [
            'id' => $row['id'],
            'title' => $row['purpose'] !== '' ? $row['purpose'] : 'Traitement documenté',
            'subtitle' => $row['legal_basis'],
            'status' => 'documented',
        ];
        foreach (array_column(self::treatmentFields(), 'id') as $id) {
            $item[$id] = $row[$id] ?? '';
        }
        return $item;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private static function sourceItem(array $row): array
    {
        $item = [
            'id' => $row['id'],
            'title' => $row['label'] !== '' ? $row['label'] : ($row['service_id'] !== '' ? $row['service_id'] : 'Source indirecte'),
            'subtitle' => $row['source'],
            'status' => $row['state'],
        ];
        foreach (array_column(self::sourceFields(), 'id') as $id) {
            $item[$id] = $row[$id] ?? '';
        }
        return $item;
    }

    /** @return array<string,mixed> */
    private static function textField(string $id, string $label, int $maxLength = 1000): array
    {
        return ['id' => $id, 'type' => 'text', 'label' => $label, 'max_length' => $maxLength];
    }

    /** @return array<string,mixed> */
    private static function multilineField(string $id, string $label): array
    {
        return ['id' => $id, 'type' => 'multiline', 'label' => $label, 'max_length' => 4000];
    }

    /** @return array<string,mixed> */
    private static function emailField(string $id, string $label): array
    {
        return ['id' => $id, 'type' => 'email', 'label' => $label, 'max_length' => 254];
    }

    /** @return array<string,mixed> */
    private static function urlField(string $id, string $label): array
    {
        return ['id' => $id, 'type' => 'url', 'label' => $label, 'max_length' => 2048];
    }

    /** @param array<string,string> $choices @return array<string,mixed> */
    private static function choiceField(string $id, string $label, array $choices): array
    {
        return [
            'id' => $id,
            'type' => 'single_choice',
            'label' => $label,
            'choices' => self::choices($choices),
        ];
    }

    /** @return array<string,string> */
    private static function yesNoUnknown(): array
    {
        return [
            'unknown' => 'Je ne sais pas encore',
            'no' => 'Non',
            'yes' => 'Oui',
        ];
    }

    /** @param array<string,string> $choices @return list<array{value:string,label:string}> */
    private static function choices(array $choices): array
    {
        $out = [];
        foreach ($choices as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }
        return $out;
    }
}
