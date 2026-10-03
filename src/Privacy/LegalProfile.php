<?php
declare(strict_types=1);

namespace Dendrila\Cms\Privacy;

final class LegalProfile
{
    private const YES_NO_UNKNOWN = ['no', 'yes', 'unknown'];
    private const LEGAL_BASES = [
        'consent', 'contract', 'legal_obligation', 'vital_interests',
        'public_task', 'legitimate_interest', 'unknown',
    ];
    private const MANDATORY = ['optional', 'contract', 'law', 'unknown'];

    /** @return array<string, mixed> */
    public static function defaults(string $locale = 'fr'): array
    {
        $fr = str_starts_with(strtolower($locale), 'fr');
        return [
            'controller_name' => '',
            'controller_address' => '',
            'privacy_contact' => '',
            'dpo_contact' => '',
            'dpo_designated' => 'no',
            'site_editor_name' => '',
            'site_editor_address' => '',
            'entity_type' => 'unknown',
            'company_country' => '',
            'company_registration_number' => '',
            'company_registration_label' => '',
            'company_siren' => '',
            'company_siret' => '',
            'company_vat' => '',
            'company_legal_form' => '',
            'company_capital' => '',
            'company_registry' => '',
            'company_activity' => '',
            'site_name' => '',
            'site_url' => '',
            'public_contact_email' => '',
            'public_contact_phone' => '',
            'publication_director' => '',
            'host_name' => '',
            'host_address' => '',
            'host_phone' => '',
            'host_source_url' => '',
            'regulated_activity_details' => '',
            'rep_idu' => '',
            'company_lookup_source' => '',
            'company_lookup_at' => '',
            'representative_applicable' => 'unknown',
            'controller_representative' => '',
            'controller_representative_contact' => '',
            'collection_mode' => 'unknown',
            'indirect_services' => [],
            'indirect_categories' => '',
            'indirect_sources' => '',
            'mandatory_information' => '',
            'transfers_status' => 'unknown',
            'transfer_destinations' => '',
            'transfer_mechanism' => '',
            'transfer_safeguards' => '',
            'automated_decision' => 'unknown',
            'automated_details' => '',
            'special_categories' => 'unknown',
            'special_categories_basis' => '',
            'criminal_data' => 'unknown',
            'criminal_data_basis' => '',
            'minors_data' => 'unknown',
            'minors_details' => '',
            'high_risk_processing' => 'unknown',
            'dpia_status' => 'unknown',
            'supervisory_authority' => $fr ? 'CNIL – Commission nationale de l’informatique et des libertés' : '',
            'supervisory_url' => $fr ? 'https://www.cnil.fr/fr/plaintes' : '',
            'cookie_nonessential' => 'unknown',
            'cookie_consent_status' => 'unknown',
            'cookie_preferences' => '',
            'cookie_choice_retention' => '',
            'cookie_cross_device' => 'unknown',
            'cookie_cross_device_explanation' => '',
            'email_pixels' => 'unknown',
            'email_pixel_purposes' => '',
            'email_pixel_status' => 'unknown',
            'email_pixel_preferences' => '',
            'tracked_links' => 'unknown',
            'tracked_links_details' => '',
            'external_email_use' => 'unknown',
            'external_bulk_email' => 'unknown',
            'external_bulk_unsubscribe' => 'unknown',
            'external_bulk_bcc' => 'unknown',
            'external_email_tools' => '',
            'external_messaging' => 'unknown',
            'external_messaging_tools' => '',
            'external_booking' => 'unknown',
            'external_booking_tools' => '',
            'external_forms' => 'unknown',
            'external_form_tools' => '',
            'external_payments' => 'unknown',
            'external_payment_tools' => '',
            'external_files' => 'unknown',
            'external_file_tools' => '',
            'backup_reviewed' => 'unknown',
            'treatments' => [],
            'wizard_reviewed_once' => false,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public static function normalize(array $input, string $locale = 'fr'): array
    {
        $defaults = self::defaults($locale);
        $unknown = array_diff(array_keys($input), array_keys($defaults));
        if ($unknown !== []) {
            throw new \InvalidArgumentException('Unknown legal profile field: ' . (string) reset($unknown));
        }

        $profile = $defaults;
        $text = [
            'controller_name','controller_address','privacy_contact','dpo_contact',
            'site_editor_name','site_editor_address','controller_representative','controller_representative_contact',
            'company_registration_number','company_registration_label','company_siren','company_siret','company_vat',
            'company_legal_form','company_capital','company_registry','company_activity','site_name','public_contact_phone',
            'publication_director','host_name','host_address','host_phone','regulated_activity_details','rep_idu',
            'company_lookup_at','indirect_categories','indirect_sources','mandatory_information','transfer_destinations',
            'transfer_mechanism','transfer_safeguards','automated_details','special_categories_basis','criminal_data_basis',
            'minors_details','supervisory_authority','cookie_preferences','cookie_choice_retention',
            'cookie_cross_device_explanation','email_pixel_purposes','email_pixel_preferences','tracked_links_details',
            'external_email_tools','external_messaging_tools','external_booking_tools','external_form_tools',
            'external_payment_tools','external_file_tools',
        ];
        foreach ($text as $field) {
            if (array_key_exists($field, $input)) {
                $profile[$field] = self::text($input[$field], $field, 4000);
            }
        }

        foreach (['supervisory_url','site_url','company_lookup_source','host_source_url'] as $field) {
            if (array_key_exists($field, $input)) {
                $profile[$field] = self::url($input[$field], $field);
            }
        }
        if (array_key_exists('public_contact_email', $input)) {
            $email = self::text($input['public_contact_email'], 'public_contact_email', 254);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new \InvalidArgumentException('public_contact_email must be a valid email address.');
            }
            $profile['public_contact_email'] = $email;
        }

        $selects = [
            'company_country' => null,
            'entity_type' => ['unknown','company','individual','association','other'],
            'representative_applicable' => self::YES_NO_UNKNOWN,
            'collection_mode' => ['direct','indirect','both','unknown'],
            'transfers_status' => self::YES_NO_UNKNOWN,
            'automated_decision' => self::YES_NO_UNKNOWN,
            'special_categories' => self::YES_NO_UNKNOWN,
            'criminal_data' => self::YES_NO_UNKNOWN,
            'minors_data' => self::YES_NO_UNKNOWN,
            'high_risk_processing' => self::YES_NO_UNKNOWN,
            'dpia_status' => ['not_required','done','to_do','unknown'],
            'cookie_nonessential' => self::YES_NO_UNKNOWN,
            'cookie_consent_status' => ['yes','no','unknown','exempt_only'],
            'cookie_cross_device' => self::YES_NO_UNKNOWN,
            'email_pixels' => self::YES_NO_UNKNOWN,
            'email_pixel_status' => ['consent','exempt_delivery','unknown'],
            'tracked_links' => self::YES_NO_UNKNOWN,
            'dpo_designated' => self::YES_NO_UNKNOWN,
            'external_email_use' => self::YES_NO_UNKNOWN,
            'external_bulk_email' => self::YES_NO_UNKNOWN,
            'external_bulk_unsubscribe' => self::YES_NO_UNKNOWN,
            'external_bulk_bcc' => self::YES_NO_UNKNOWN,
            'external_messaging' => self::YES_NO_UNKNOWN,
            'external_booking' => self::YES_NO_UNKNOWN,
            'external_forms' => self::YES_NO_UNKNOWN,
            'external_payments' => self::YES_NO_UNKNOWN,
            'external_files' => self::YES_NO_UNKNOWN,
            'backup_reviewed' => self::YES_NO_UNKNOWN,
        ];
        foreach ($selects as $field => $allowed) {
            if (!array_key_exists($field, $input)) {
                continue;
            }
            $value = self::identifierOrEmpty($input[$field], $field);
            if ($allowed !== null && !in_array($value, $allowed, true)) {
                throw new \InvalidArgumentException("Invalid legal profile value for {$field}.");
            }
            if ($field === 'company_country' && $value !== '' && preg_match('/^[a-z]{2}$/', $value) !== 1) {
                throw new \InvalidArgumentException('company_country must be a two-letter country code.');
            }
            $profile[$field] = $value;
        }

        if (array_key_exists('treatments', $input)) {
            $profile['treatments'] = self::treatments($input['treatments']);
        }
        if (array_key_exists('indirect_services', $input)) {
            $profile['indirect_services'] = self::indirectServices($input['indirect_services']);
        }
        if (array_key_exists('wizard_reviewed_once', $input)) {
            if (!is_bool($input['wizard_reviewed_once'])) {
                throw new \InvalidArgumentException('wizard_reviewed_once must be a boolean.');
            }
            $profile['wizard_reviewed_once'] = $input['wizard_reviewed_once'];
        }

        self::assertJsonSize($profile, 196_608);
        return $profile;
    }

    /** @param array<string, mixed> $current @param array<string, mixed> $changes @return array<string, mixed> */
    public static function merge(array $current, array $changes, string $locale = 'fr'): array
    {
        $normalized = self::normalize($current, $locale);
        return self::normalize(array_replace($normalized, $changes), $locale);
    }

    /** @param array<string, mixed> $profile @return array<string, array{complete:bool,missing:list<string>}> */
    public static function progress(array $profile, string $locale = 'fr'): array
    {
        $p = self::normalize($profile, $locale);
        $status = [];
        foreach (['identity','treatments','flows','external','risk','cookies','email','authority'] as $section) {
            $status[$section] = self::sectionStatus($p, $section);
        }
        return $status;
    }

    /** @param array<string, mixed> $profile @return array{complete:bool,missing:list<string>} */
    public static function sectionStatus(array $profile, string $section): array
    {
        $missing = [];
        if ($section === 'identity') {
            if ($profile['site_editor_name'] === '' && $profile['controller_name'] === '') {
                $missing[] = 'site_editor';
            }
            if ($profile['controller_name'] === '') {
                $missing[] = 'controller';
            }
            if ($profile['privacy_contact'] === '') {
                $missing[] = 'privacy_contact';
            }
            if ($profile['dpo_designated'] === 'yes' && $profile['dpo_contact'] === '') {
                $missing[] = 'dpo_contact';
            }
        } elseif ($section === 'treatments') {
            if ($profile['treatments'] === []) {
                $missing[] = 'treatment';
            }
        } elseif ($section === 'flows') {
            if (in_array($profile['collection_mode'], ['indirect','both'], true)) {
                if ($profile['indirect_services'] === [] && $profile['indirect_sources'] === '') {
                    $missing[] = 'indirect_source';
                }
                foreach ($profile['indirect_services'] as $service) {
                    if ($service['categories'] === '' || $service['source'] === '') {
                        $missing[] = 'indirect_service_details';
                        break;
                    }
                }
            }
            if ($profile['transfers_status'] === 'yes' && $profile['transfer_destinations'] === '') {
                $missing[] = 'transfer_destination';
            }
            if ($profile['automated_decision'] === 'yes' && $profile['automated_details'] === '') {
                $missing[] = 'automated_decision_details';
            }
        } elseif ($section === 'risk') {
            if ($profile['special_categories'] === 'yes' && $profile['special_categories_basis'] === '') {
                $missing[] = 'special_categories_basis';
            }
            if ($profile['criminal_data'] === 'yes' && $profile['criminal_data_basis'] === '') {
                $missing[] = 'criminal_data_basis';
            }
            if ($profile['minors_data'] === 'yes' && $profile['minors_details'] === '') {
                $missing[] = 'minors_details';
            }
        } elseif ($section === 'cookies') {
            if ($profile['cookie_nonessential'] === 'yes' && !in_array($profile['cookie_consent_status'], ['yes','exempt_only'], true)) {
                $missing[] = 'cookie_consent';
            }
        } elseif ($section === 'email') {
            if ($profile['email_pixels'] === 'yes' && $profile['email_pixel_purposes'] === '') {
                $missing[] = 'email_pixel_purposes';
            }
            if ($profile['tracked_links'] === 'yes' && $profile['tracked_links_details'] === '') {
                $missing[] = 'tracked_links_details';
            }
        } elseif ($section === 'authority') {
            if ($profile['supervisory_authority'] === '') {
                $missing[] = 'supervisory_authority';
            }
        } elseif ($section !== 'external') {
            throw new \InvalidArgumentException('Unknown legal profile section.');
        }

        return ['complete' => $missing === [], 'missing' => array_values(array_unique($missing))];
    }

    private static function text(mixed $value, string $field, int $max): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$field} must be a string.");
        }
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
        if (mb_strlen($value) > $max) {
            throw new \InvalidArgumentException("{$field} is too long.");
        }
        return $value;
    }

    private static function url(mixed $value, string $field): string
    {
        $value = self::text($value, $field, 2048);
        if ($value === '') {
            return '';
        }
        if (filter_var($value, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http','https'], true)) {
            throw new \InvalidArgumentException("{$field} must be an HTTP(S) URL.");
        }
        return $value;
    }

    private static function identifierOrEmpty(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException("{$field} must be a string.");
        }
        $value = strtolower(trim($value));
        if ($value !== '' && preg_match('/^[a-z0-9_-]{1,64}$/', $value) !== 1) {
            throw new \InvalidArgumentException("{$field} contains invalid characters.");
        }
        return $value;
    }

    /** @return list<array<string, mixed>> */
    private static function treatments(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 8) {
            throw new \InvalidArgumentException('treatments must be a list of at most 8 items.');
        }
        $result = [];
        $ids = [];
        foreach ($value as $row) {
            if (!is_array($row) || ($row !== [] && array_is_list($row))) {
                throw new \InvalidArgumentException('Each treatment must be an object.');
            }
            $unknown = array_diff(array_keys($row), [
                'id','purpose','data_categories','legal_basis','basis_detail',
                'recipients','retention','mandatory','consequences',
            ]);
            if ($unknown !== []) {
                throw new \InvalidArgumentException('Unknown treatment field.');
            }
            $id = self::rowId($row['id'] ?? null);
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException('Duplicate treatment id.');
            }
            $ids[$id] = true;
            $basis = self::identifierOrEmpty($row['legal_basis'] ?? 'unknown', 'legal_basis');
            if (!in_array($basis, self::LEGAL_BASES, true)) {
                throw new \InvalidArgumentException('Invalid treatment legal basis.');
            }
            $mandatory = self::identifierOrEmpty($row['mandatory'] ?? 'unknown', 'mandatory');
            if (!in_array($mandatory, self::MANDATORY, true)) {
                throw new \InvalidArgumentException('Invalid treatment mandatory value.');
            }
            $entry = [
                'id' => $id,
                'purpose' => self::text($row['purpose'] ?? '', 'purpose', 1000),
                'data_categories' => self::text($row['data_categories'] ?? '', 'data_categories', 2000),
                'legal_basis' => $basis,
                'basis_detail' => self::text($row['basis_detail'] ?? '', 'basis_detail', 2000),
                'recipients' => self::text($row['recipients'] ?? '', 'recipients', 2000),
                'retention' => self::text($row['retention'] ?? '', 'retention', 1000),
                'mandatory' => $mandatory,
                'consequences' => self::text($row['consequences'] ?? '', 'consequences', 2000),
            ];
            if ($entry['purpose'] === '' && $entry['data_categories'] === ''
                && $entry['recipients'] === '' && $entry['retention'] === '') {
                throw new \InvalidArgumentException('A treatment must contain useful information.');
            }
            $result[] = $entry;
        }
        return $result;
    }

    /** @return list<array<string, mixed>> */
    private static function indirectServices(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 20) {
            throw new \InvalidArgumentException('indirect_services must be a list of at most 20 items.');
        }
        $result = [];
        $ids = [];
        foreach ($value as $service) {
            if (!is_array($service) || ($service !== [] && array_is_list($service))) {
                throw new \InvalidArgumentException('Each indirect service must be an object.');
            }
            $unknown = array_diff(array_keys($service), [
                'id','service_id','label','state','categories','source','recipients',
                'transfer_status','transfer_destinations','transfer_mechanism',
                'transfer_safeguards','automated_decision','automated_details',
            ]);
            if ($unknown !== []) {
                throw new \InvalidArgumentException('Unknown indirect service field.');
            }
            $id = self::rowId($service['id'] ?? null);
            if (isset($ids[$id])) {
                throw new \InvalidArgumentException('Duplicate indirect service id.');
            }
            $ids[$id] = true;
            $serviceId = self::identifierOrEmpty($service['service_id'] ?? '', 'service_id');
            $state = self::identifierOrEmpty($service['state'] ?? 'manual', 'state');
            if (!in_array($state, ['active','potential','manual'], true)) {
                throw new \InvalidArgumentException('Invalid indirect service state.');
            }
            $transfer = self::identifierOrEmpty($service['transfer_status'] ?? 'unknown', 'transfer_status');
            $automated = self::identifierOrEmpty($service['automated_decision'] ?? 'unknown', 'automated_decision');
            if (!in_array($transfer, self::YES_NO_UNKNOWN, true)
                || !in_array($automated, self::YES_NO_UNKNOWN, true)) {
                throw new \InvalidArgumentException('Invalid indirect service yes/no/unknown value.');
            }
            $entry = [
                'id' => $id,
                'service_id' => $serviceId,
                'label' => self::text($service['label'] ?? '', 'label', 190),
                'state' => $state,
                'categories' => self::text($service['categories'] ?? '', 'categories', 2000),
                'source' => self::text($service['source'] ?? '', 'source', 2000),
                'recipients' => self::text($service['recipients'] ?? '', 'recipients', 2000),
                'transfer_status' => $transfer,
                'transfer_destinations' => self::text($service['transfer_destinations'] ?? '', 'transfer_destinations', 2000),
                'transfer_mechanism' => self::text($service['transfer_mechanism'] ?? '', 'transfer_mechanism', 2000),
                'transfer_safeguards' => self::text($service['transfer_safeguards'] ?? '', 'transfer_safeguards', 2000),
                'automated_decision' => $automated,
                'automated_details' => self::text($service['automated_details'] ?? '', 'automated_details', 2000),
            ];
            if ($entry['label'] === '' && $entry['source'] === '' && $entry['categories'] === '') {
                throw new \InvalidArgumentException('An indirect service must contain useful information.');
            }
            $result[] = $entry;
        }
        return $result;
    }

    private static function rowId(mixed $value): string
    {
        if ($value === null || $value === '') {
            return bin2hex(random_bytes(16));
        }
        if (!is_string($value) || preg_match('/^[a-f0-9]{32}$/', $value) !== 1) {
            throw new \InvalidArgumentException('List item id must be 32 lowercase hexadecimal characters.');
        }
        return $value;
    }

    private static function assertJsonSize(array $profile, int $maxBytes): void
    {
        $json = json_encode($profile, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($json) > $maxBytes) {
            throw new \InvalidArgumentException('Legal profile is too large.');
        }
    }
}
