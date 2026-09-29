<?php
declare(strict_types=1);

namespace LePotager\Cms\Api;

use PDO;
use RuntimeException;

final readonly class PdoSiteReadModel implements SiteReadModel
{
    public function __construct(private PDO $pdo)
    {
    }

    public function configuration(string $siteId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.site_id, s.display_name, s.config_version, s.policy_json,
                    a.branding_json, a.modules_json
             FROM sites s
             LEFT JOIN site_api_settings a ON a.site_id = s.site_id
             WHERE s.site_id = :site_id LIMIT 1'
        );
        $statement->execute(['site_id' => $siteId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Authenticated site no longer exists.');
        }
        $policy = self::decodeObject($row['policy_json'], 'site policy');
        $branding = self::decodeObject($row['branding_json'] ?? '{}', 'site branding');
        $modules = self::decodeList($row['modules_json'] ?? '[]', 'site modules');
        return [
            'schema_version' => 1,
            'config_version' => (int) $row['config_version'],
            'site' => ['id' => $row['site_id'], 'display_name' => $row['display_name']],
            'branding' => self::safeBranding($branding),
            'policy' => [
                'review_before_publish' => ($policy['review_before_publish'] ?? false) === true,
                'allow_offline_queue' => ($policy['allow_offline_queue'] ?? false) === true,
            ],
            'modules' => self::safeModules($modules),
        ];
    }

    public function snapshot(string $siteId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.config_version, s.data_revision, a.modules_json
             FROM sites s
             LEFT JOIN site_api_settings a ON a.site_id = s.site_id
             WHERE s.site_id = :site_id LIMIT 1'
        );
        $statement->execute(['site_id' => $siteId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Authenticated site no longer exists.');
        }
        $modules = self::safeModules(self::decodeList($row['modules_json'] ?? '[]', 'site modules'));
        $data = [];
        foreach ($modules as $module) {
            $data[$module['id']] = in_array($module['kind'], ['gallery', 'requests', 'records', 'calendar'], true)
                ? ['items' => []] : new \stdClass();
        }
        return [
            'schema_version' => 1,
            'config_version' => (int) $row['config_version'],
            'revision' => (int) $row['data_revision'],
            'data' => (object) $data,
        ];
    }

    /** @return array<string, mixed> */
    private static function decodeObject(mixed $json, string $label): array
    {
        try {
            $value = json_decode((string) $json, false, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException("Stored {$label} is invalid JSON.", previous: $error);
        }
        if (!$value instanceof \stdClass) {
            throw new RuntimeException("Stored {$label} must be a JSON object.");
        }
        return (array) $value;
    }

    /** @return list<array<string, mixed>> */
    private static function decodeList(mixed $json, string $label): array
    {
        try {
            $value = json_decode((string) $json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException("Stored {$label} is invalid JSON.", previous: $error);
        }
        if (!is_array($value) || !array_is_list($value)) {
            throw new RuntimeException("Stored {$label} must be a JSON list.");
        }
        return $value;
    }

    /** @param array<string, mixed> $branding @return array<string, string> */
    private static function safeBranding(array $branding): array
    {
        $safe = [];
        if (isset($branding['primary']) && is_string($branding['primary'])
            && preg_match('/^#[0-9a-fA-F]{6}$/', $branding['primary'])) {
            $safe['primary'] = $branding['primary'];
        }
        if (isset($branding['logo_url']) && is_string($branding['logo_url'])
            && filter_var($branding['logo_url'], FILTER_VALIDATE_URL)
            && strtolower((string) parse_url($branding['logo_url'], PHP_URL_SCHEME)) === 'https') {
            $safe['logo_url'] = $branding['logo_url'];
        }
        return $safe;
    }

    /** @param list<array<string, mixed>> $modules @return list<array<string, mixed>> */
    private static function safeModules(array $modules): array
    {
        $safe = [];
        $seen = [];
        $kinds = ['dashboard', 'form', 'gallery', 'requests', 'records', 'calendar', 'settings'];
        $fieldTypes = ['text', 'multiline', 'boolean', 'single_choice', 'number', 'url', 'email'];
        if (count($modules) > 50) {
            throw new RuntimeException('Stored MMW configuration has too many modules.');
        }
        foreach ($modules as $module) {
            if (!is_array($module) || !isset($module['id'], $module['kind'], $module['title'])
                || !is_string($module['id']) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $module['id'])
                || isset($seen[$module['id']]) || !is_string($module['kind'])
                || !in_array($module['kind'], $kinds, true) || !is_string($module['title'])
                || trim($module['title']) === '' || strlen($module['title']) > 120) {
                throw new RuntimeException('Stored MMW module definition is invalid.');
            }
            $seen[$module['id']] = true;
            $entry = ['id' => $module['id'], 'kind' => $module['kind'], 'title' => trim($module['title'])];
            if (isset($module['fields'])) {
                if (!is_array($module['fields']) || !array_is_list($module['fields']) || count($module['fields']) > 100) {
                    throw new RuntimeException('Stored MMW field definitions are invalid.');
                }
                $entry['fields'] = [];
                $fieldIds = [];
                foreach ($module['fields'] as $field) {
                    if (!is_array($field) || !isset($field['id'], $field['type'], $field['label'])
                        || !is_string($field['id']) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field['id'])
                        || isset($fieldIds[$field['id']]) || !is_string($field['type'])
                        || !in_array($field['type'], $fieldTypes, true) || !is_string($field['label'])
                        || trim($field['label']) === '' || strlen($field['label']) > 120) {
                        throw new RuntimeException('Stored MMW field definition is invalid.');
                    }
                    $fieldIds[$field['id']] = true;
                    $safeField = ['id' => $field['id'], 'type' => $field['type'], 'label' => trim($field['label'])];
                    foreach (['required' => 'bool', 'max_length' => 'int', 'min' => 'number', 'max' => 'number'] as $key => $type) {
                        if (!array_key_exists($key, $field)) {
                            continue;
                        }
                        $valid = match ($type) {
                            'bool' => is_bool($field[$key]),
                            'int' => is_int($field[$key]) && $field[$key] > 0,
                            'number' => is_int($field[$key]) || is_float($field[$key]),
                        };
                        if (!$valid) {
                            throw new RuntimeException("Stored MMW field option '{$key}' is invalid.");
                        }
                        $safeField[$key] = $field[$key];
                    }
                    if (isset($field['choices'])) {
                        if (!is_array($field['choices']) || !array_is_list($field['choices'])
                            || $field['choices'] === []
                            || count(array_filter($field['choices'], static fn (mixed $choice): bool =>
                                is_string($choice) && trim($choice) !== '' && strlen($choice) <= 120)) !== count($field['choices'])) {
                            throw new RuntimeException('Stored MMW choices are invalid.');
                        }
                        $safeField['choices'] = $field['choices'];
                    }
                    if ($field['type'] === 'single_choice' && !isset($safeField['choices'])) {
                        throw new RuntimeException('A single_choice field must define choices.');
                    }
                    if (isset($safeField['min'], $safeField['max']) && $safeField['min'] > $safeField['max']) {
                        throw new RuntimeException('Stored MMW field range is invalid.');
                    }
                    $entry['fields'][] = $safeField;
                }
            }
            if (isset($module['actions'])) {
                if (!is_array($module['actions']) || !array_is_list($module['actions']) || count($module['actions']) > 50) {
                    throw new RuntimeException('Stored MMW actions are invalid.');
                }
                $entry['actions'] = [];
                $actionIds = [];
                foreach ($module['actions'] as $action) {
                    if (!is_array($action) || !isset($action['id'], $action['label'])
                        || !is_string($action['id']) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $action['id'])
                        || isset($actionIds[$action['id']]) || !is_string($action['label'])
                        || trim($action['label']) === '' || strlen($action['label']) > 120) {
                        throw new RuntimeException('Stored MMW action is invalid.');
                    }
                    $actionIds[$action['id']] = true;
                    $safeAction = ['id' => $action['id'], 'label' => trim($action['label'])];
                    if (isset($action['tone'])) {
                        if (!in_array($action['tone'], ['primary', 'secondary', 'danger'], true)) {
                            throw new RuntimeException('Stored MMW action tone is invalid.');
                        }
                        $safeAction['tone'] = $action['tone'];
                    }
                    foreach (['requires_confirmation', 'allow_offline'] as $key) {
                        if (isset($action[$key])) {
                            if (!is_bool($action[$key])) {
                                throw new RuntimeException("Stored MMW action option '{$key}' is invalid.");
                            }
                            $safeAction[$key] = $action[$key];
                        }
                    }
                    if (isset($action['confirmation_text']) && is_string($action['confirmation_text'])) {
                        $safeAction['confirmation_text'] = mb_substr($action['confirmation_text'], 0, 500);
                    }
                    $safeAction['allow_offline'] = $safeAction['allow_offline'] ?? false;
                    $entry['actions'][] = $safeAction;
                }
            }
            if (isset($module['options']) && is_array($module['options']) && !array_is_list($module['options'])) {
                $options = array_intersect_key($module['options'], array_flip([
                    'upload_enabled', 'max_bytes', 'accepted_mime_types', 'allow_create', 'allow_update', 'allow_delete',
                ]));
                foreach (['upload_enabled', 'allow_create', 'allow_update', 'allow_delete'] as $key) {
                    if (isset($options[$key]) && !is_bool($options[$key])) {
                        throw new RuntimeException("Stored MMW module option '{$key}' is invalid.");
                    }
                }
                if (isset($options['max_bytes'])
                    && (!is_int($options['max_bytes']) || $options['max_bytes'] < 1 || $options['max_bytes'] > 25_000_000)) {
                    throw new RuntimeException('Stored MMW upload limit is invalid.');
                }
                if (isset($options['accepted_mime_types'])) {
                    if (!is_array($options['accepted_mime_types']) || !array_is_list($options['accepted_mime_types'])
                        || count(array_filter($options['accepted_mime_types'], static fn (mixed $mime): bool =>
                            is_string($mime) && preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i', $mime) === 1))
                            !== count($options['accepted_mime_types'])) {
                        throw new RuntimeException('Stored MMW accepted MIME types are invalid.');
                    }
                }
                if ($options !== []) {
                    $entry['options'] = $options;
                }
            }
            $safe[] = $entry;
        }
        return $safe;
    }
}
