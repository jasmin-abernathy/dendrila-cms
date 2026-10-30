<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\ContentNotFound;
use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Privacy\PdoPrivacyRepository;
use Dendrila\Cms\Privacy\PrivacyService;
use Dendrila\Cms\Storage\PdoCmsStore;
use PDO;
use RuntimeException;

final readonly class PdoSiteReadModel implements SiteReadModel
{
    private PdoCmsStore $store;
    private ContentService $contentService;
    private PrivacyService $privacy;

    public function __construct(private PDO $pdo, private ?\Dendrila\Cms\Media\MediaService $media = null)
    {
        $this->store = new PdoCmsStore($pdo);
        $authorization = new AuthorizationService($this->store);
        $this->contentService = new ContentService($this->store, $authorization);
        $this->privacy = new PrivacyService(new PdoPrivacyRepository($pdo), $authorization);
    }

    public function configuration(ActorContext $actor): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.site_id, s.display_name, s.config_version, s.policy_json,
                    a.branding_json, a.modules_json
             FROM sites s
             LEFT JOIN site_api_settings a ON a.site_id = s.site_id
             WHERE s.site_id = :site_id LIMIT 1'
        );
        $statement->execute(['site_id' => $actor->siteId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Authenticated site no longer exists.');
        }
        $policy = self::decodeObject($row['policy_json'], 'site policy');
        $branding = self::decodeObject($row['branding_json'] ?? '{}', 'site branding');
        $modules = self::decodeList($row['modules_json'] ?? '[]', 'site modules');
        PrivacySettingsModule::assertReservedIdAvailable($modules);
        PrivacyLegalModules::assertReservedIdsAvailable($modules);
        PrivacyDocumentsModule::assertReservedIdAvailable($modules);
        $safeModules = [];
        foreach (self::safeModules($modules) as $module) {
            if ($module['kind'] === 'media_library') {
                if ($this->media === null || !$this->store->actorHasPermission($actor->siteId, $actor->actorId, 'media.read')) continue;
                $update = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'media.update');
                $delete = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'media.delete');
                $upload = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'media.upload');
                $writable = ($module['writable'] ?? false) === true;
                $module['writable'] = $writable && ($update || $delete || $upload);
                $module['options']['allow_update'] = $writable && $update;
                $module['options']['allow_rotate'] = $writable && $update && ($module['options']['allow_rotate'] ?? false);
                $module['options']['allow_delete'] = $writable && $delete && ($module['options']['allow_delete'] ?? false);
                if (is_array($module['media'] ?? null)) $module['media']['upload_enabled'] = $writable && $upload && ($module['media']['upload_enabled'] ?? false);
            }
            $safeModules[] = $module;
        }
        $privacyState = $this->privacyState($actor);
        if ($privacyState !== null) {
            $writablePrivacy = $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'privacy.manage');
            $safeModules[] = self::safeModules([
                PrivacySettingsModule::definition($writablePrivacy),
            ])[0];
            foreach (self::safeModules(PrivacyLegalModules::definitions($writablePrivacy)) as $privacyModule) {
                $safeModules[] = $privacyModule;
            }
            $safeModules[] = self::safeModules([
                PrivacyDocumentsModule::definition($writablePrivacy),
            ])[0];
        }
        return [
            'schema_version' => 1,
            'config_version' => (int) $row['config_version'],
            'site' => ['id' => $row['site_id'], 'display_name' => $row['display_name']],
            'branding' => (object) self::safeBranding($branding),
            'policy' => [
                'review_before_publish' => ($policy['review_before_publish'] ?? false) === true,
                'allow_offline_queue' => ($policy['allow_offline_queue'] ?? false) === true,
            ],
            'modules' => $safeModules,
        ];
    }

    public function snapshot(ActorContext $actor): array
    {
        $statement = $this->pdo->prepare(
            'SELECT s.config_version, s.data_revision, a.modules_json
             FROM sites s
             LEFT JOIN site_api_settings a ON a.site_id = s.site_id
             WHERE s.site_id = :site_id LIMIT 1'
        );
        $statement->execute(['site_id' => $actor->siteId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            throw new RuntimeException('Authenticated site no longer exists.');
        }
        $storedModules = self::decodeList($row['modules_json'] ?? '[]', 'site modules');
        PrivacySettingsModule::assertReservedIdAvailable($storedModules);
        PrivacyLegalModules::assertReservedIdsAvailable($storedModules);
        PrivacyDocumentsModule::assertReservedIdAvailable($storedModules);
        self::safeModules($storedModules);
        $data = (new SnapshotProjector())->project(
            $storedModules,
            function (string $typeId, string $contentId) use ($actor): ?array {
                try {
                    return $this->contentService->get($actor, $contentId);
                } catch (ContentNotFound) {
                    return null;
                }
            },
            fn (string $typeId, int $limit): array => $this->contentService->list($actor, [
                'type' => $typeId,
                'limit' => $limit,
                'offset' => 0,
            ]),
        );
        foreach (self::safeModules($storedModules) as $module) {
            if ($module['kind'] !== 'media_library') continue;
            unset($data[$module['id']]);
            if ($this->media !== null && $this->store->actorHasPermission($actor->siteId, $actor->actorId, 'media.read')) {
                $data[$module['id']] = ['items' => $this->media->items($actor, $module)];
            }
        }
        $privacyState = $this->privacyState($actor);
        if ($privacyState !== null) {
            $data[PrivacySettingsModule::ID] = PrivacySettingsModule::values($privacyState['settings']);
            foreach (PrivacyLegalModules::snapshot($privacyState['settings']['legal_profile']) as $moduleId => $moduleData) {
                $data[$moduleId] = $moduleData;
            }
            $data[PrivacyDocumentsModule::ID] = PrivacyDocumentsModule::snapshot(
                $this->privacy->legalDocumentsState($actor),
            );
        }
        return [
            'schema_version' => 1,
            'config_version' => (int) $row['config_version'],
            'revision' => (int) $row['data_revision'],
            'data' => (object) $data,
        ];
    }

    /** @return array<string, mixed>|null */
    private function privacyState(ActorContext $actor): ?array
    {
        try {
            return $this->privacy->state($actor);
        } catch (AccessDenied) {
            return null;
        }
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
        foreach (['primary', 'secondary'] as $key) {
            if (isset($branding[$key]) && is_string($branding[$key])
                && preg_match('/^#[0-9a-fA-F]{6}$/', $branding[$key])) {
                $safe[$key] = $branding[$key];
            }
        }
        foreach (['logo_url', 'wordmark_url'] as $key) {
            if (isset($branding[$key]) && is_string($branding[$key])
                && filter_var($branding[$key], FILTER_VALIDATE_URL)
                && strtolower((string) parse_url($branding[$key], PHP_URL_SCHEME)) === 'https') {
                $safe[$key] = $branding[$key];
            }
        }
        return $safe;
    }

    /** @param list<array<string, mixed>> $modules @return list<array<string, mixed>> */
    public static function safeModules(array $modules): array
    {
        $safe = [];
        $seen = [];
        $kinds = ['dashboard', 'form', 'gallery', 'requests', 'records', 'calendar', 'settings', 'media_library'];
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
            self::validateSource($module['source'] ?? null, $module['kind']);
            $entry = ['id' => $module['id'], 'kind' => $module['kind'], 'title' => trim($module['title'])];
            if (isset($module['subtitle'])) {
                if (!is_string($module['subtitle']) || strlen($module['subtitle']) > 255) {
                    throw new RuntimeException('Stored MMW module subtitle is invalid.');
                }
                $entry['subtitle'] = trim($module['subtitle']);
            }
            if (isset($module['icon'])) {
                if (!is_string($module['icon']) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $module['icon'])) {
                    throw new RuntimeException('Stored MMW module icon is invalid.');
                }
                $entry['icon'] = $module['icon'];
            }
            if (isset($module['order'])) {
                if (!is_int($module['order']) || abs($module['order']) > 100_000) {
                    throw new RuntimeException('Stored MMW module order is invalid.');
                }
                $entry['order'] = $module['order'];
            }
            if (isset($module['writable'])) {
                if (!is_bool($module['writable'])) {
                    throw new RuntimeException('Stored MMW module writable flag is invalid.');
                }
                $entry['writable'] = $module['writable'];
            }
            if (isset($module['fields'])) {
                $entry['fields'] = self::safeFields($module['fields'], 'module');
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
                    if (isset($action['confirmation_text'])) {
                        if (!is_string($action['confirmation_text'])) {
                            throw new RuntimeException('Stored MMW confirmation text is invalid.');
                        }
                        $safeAction['confirmation_text'] = mb_substr($action['confirmation_text'], 0, 500);
                    }
                    $safeAction['allow_offline'] = $safeAction['allow_offline'] ?? false;
                    $entry['actions'][] = $safeAction;
                }
            }
            if (isset($module['options']) && is_array($module['options']) && !array_is_list($module['options'])) {
                $options = array_intersect_key($module['options'], array_flip([
                    'upload_enabled', 'max_bytes', 'accepted_mime_types', 'allow_create', 'allow_update', 'allow_delete', 'allow_rotate',
                    'group_id', 'group_title', 'group_description', 'group_order', 'field_sections',
                    'item_label', 'empty_message',
                ]));
                foreach (['upload_enabled', 'allow_create', 'allow_update', 'allow_delete', 'allow_rotate'] as $key) {
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
                foreach ([
                    'group_title' => 120,
                    'group_description' => 500,
                    'item_label' => 80,
                    'empty_message' => 255,
                ] as $key => $maxLength) {
                    if (isset($options[$key])
                        && (!is_string($options[$key]) || trim($options[$key]) === ''
                            || mb_strlen($options[$key]) > $maxLength)) {
                        throw new RuntimeException("Stored MMW module option '{$key}' is invalid.");
                    }
                    if (isset($options[$key])) {
                        $options[$key] = trim($options[$key]);
                    }
                }
                if (isset($options['group_id'])
                    && (!is_string($options['group_id'])
                        || preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $options['group_id']) !== 1)) {
                    throw new RuntimeException("Stored MMW module option 'group_id' is invalid.");
                }
                if (isset($options['group_id']) xor isset($options['group_title'])) {
                    throw new RuntimeException('Stored MMW module grouping requires both group_id and group_title.');
                }
                if (isset($options['group_order'])
                    && (!is_int($options['group_order']) || abs($options['group_order']) > 100_000)) {
                    throw new RuntimeException("Stored MMW module option 'group_order' is invalid.");
                }
                if ((isset($options['item_label']) || isset($options['empty_message']))
                    && !in_array($module['kind'], ['records', 'gallery'], true)) {
                    throw new RuntimeException('Stored MMW collection labels require a records or gallery module.');
                }
                if (isset($options['field_sections'])) {
                    if ($module['kind'] !== 'settings') {
                        throw new RuntimeException('Stored MMW field sections are only supported for settings modules.');
                    }
                    if (!is_array($options['field_sections']) || !array_is_list($options['field_sections'])
                        || $options['field_sections'] === [] || count($options['field_sections']) > 20) {
                        throw new RuntimeException('Stored MMW field sections are invalid.');
                    }
                    $knownFields = array_fill_keys(array_column($entry['fields'] ?? [], 'id'), true);
                    if ($knownFields === []) {
                        throw new RuntimeException('Stored MMW field sections require module fields.');
                    }
                    $safeSections = [];
                    $sectionIds = [];
                    $seenFields = [];
                    foreach ($options['field_sections'] as $section) {
                        if (!is_array($section) || !is_string($section['id'] ?? null)
                            || preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $section['id']) !== 1
                            || isset($sectionIds[$section['id']])
                            || !is_string($section['title'] ?? null) || trim($section['title']) === ''
                            || mb_strlen($section['title']) > 120
                            || !is_array($section['field_ids'] ?? null) || !array_is_list($section['field_ids'])
                            || $section['field_ids'] === [] || count($section['field_ids']) > 100) {
                            throw new RuntimeException('Stored MMW field section is invalid.');
                        }
                        $sectionIds[$section['id']] = true;
                        $safeSection = [
                            'id' => $section['id'],
                            'title' => trim($section['title']),
                            'field_ids' => [],
                        ];
                        if (isset($section['description'])) {
                            if (!is_string($section['description']) || trim($section['description']) === ''
                                || mb_strlen($section['description']) > 500) {
                                throw new RuntimeException('Stored MMW field section description is invalid.');
                            }
                            $safeSection['description'] = trim($section['description']);
                        }
                        foreach ($section['field_ids'] as $fieldId) {
                            if (!is_string($fieldId) || !isset($knownFields[$fieldId]) || isset($seenFields[$fieldId])) {
                                throw new RuntimeException('Stored MMW field section references an invalid or repeated field.');
                            }
                            $seenFields[$fieldId] = true;
                            $safeSection['field_ids'][] = $fieldId;
                        }
                        $safeSections[] = $safeSection;
                    }
                    if (count($seenFields) !== count($knownFields)) {
                        throw new RuntimeException('Stored MMW field sections must cover every module field exactly once.');
                    }
                    $options['field_sections'] = $safeSections;
                }
                if ($options !== []) {
                    $entry['options'] = $options;
                }
            }
            if (isset($module['media'])) {
                $media = self::safeMedia($module['media']);
                $entry['media'] = $media === [] ? new \stdClass() : $media;
            }
            if ($module['kind'] === 'media_library') {
                if (isset($module['options']) && (!is_array($module['options']) || ($module['options'] !== [] && array_is_list($module['options'])))) throw new RuntimeException('Media options must be an object.');
                foreach (['fields' => $entry['fields'] ?? [], 'media' => is_array($entry['media'] ?? null) ? ($entry['media']['fields'] ?? []) : []] as $fields) {
                    foreach ($fields as $field) {
                        if (!in_array($field['id'], ['title', 'alt', 'caption', 'credit', 'kind', 'position'], true)
                            || ($field['id'] === 'position' ? $field['type'] !== 'number' : !in_array($field['type'], ['text', 'multiline', 'single_choice', 'url', 'email'], true))) {
                            throw new RuntimeException('Unsupported native media field.');
                        }
                    }
                }
                foreach (['allow_rotate', 'allow_delete'] as $option) {
                    if (array_key_exists($option, $module['options'] ?? []) && !is_bool($module['options'][$option])) throw new RuntimeException('Invalid media capability.');
                }
            }
            $safe[] = $entry;
        }
        return $safe;
    }

    /** @return list<array<string, mixed>> */
    private static function safeFields(mixed $fields, string $context): array
    {
        $fieldTypes = ['text', 'multiline', 'boolean', 'single_choice', 'number', 'url', 'email'];
        if (!is_array($fields) || !array_is_list($fields) || count($fields) > 100) {
            throw new RuntimeException("Stored MMW {$context} field definitions are invalid.");
        }
        $safe = [];
        $fieldIds = [];
        foreach ($fields as $field) {
            if (!is_array($field) || !isset($field['id'], $field['type'], $field['label'])
                || !is_string($field['id']) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field['id'])
                || isset($fieldIds[$field['id']]) || !is_string($field['type'])
                || !in_array($field['type'], $fieldTypes, true) || !is_string($field['label'])
                || trim($field['label']) === '' || strlen($field['label']) > 120) {
                throw new RuntimeException("Stored MMW {$context} field definition is invalid.");
            }
            $fieldIds[$field['id']] = true;
            $safeField = ['id' => $field['id'], 'type' => $field['type'], 'label' => trim($field['label'])];
            if (isset($field['hint'])) {
                if (!is_string($field['hint']) || strlen($field['hint']) > 300) {
                    throw new RuntimeException("Stored MMW {$context} field hint is invalid.");
                }
                $safeField['hint'] = $field['hint'];
            }
            if (isset($field['source_field_id']) && (!is_string($field['source_field_id'])
                || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field['source_field_id']))) {
                throw new RuntimeException('Stored MMW source field mapping is invalid.');
            }
            foreach (['required' => 'bool', 'max_length' => 'int', 'min' => 'number', 'max' => 'number'] as $key => $type) {
                if (!array_key_exists($key, $field)) {
                    continue;
                }
                $valid = match ($type) {
                    'bool' => is_bool($field[$key]),
                    'int' => is_int($field[$key]) && $field[$key] > 0 && $field[$key] <= 1_000_000,
                    'number' => (is_int($field[$key]) || is_float($field[$key])) && is_finite((float) $field[$key]),
                };
                if (!$valid) {
                    throw new RuntimeException("Stored MMW field option '{$key}' is invalid.");
                }
                $safeField[$key] = $field[$key];
            }
            if (isset($field['choices'])) {
                if (!is_array($field['choices']) || !array_is_list($field['choices'])
                    || $field['choices'] === [] || count($field['choices']) > 100) {
                    throw new RuntimeException('Stored MMW choices are invalid.');
                }
                $choices = [];
                $choiceValues = [];
                foreach ($field['choices'] as $choice) {
                    if (is_string($choice)) {
                        $value = trim($choice);
                        $label = $value;
                    } elseif (is_array($choice) && is_string($choice['value'] ?? null)
                        && is_string($choice['label'] ?? null)) {
                        $value = trim($choice['value']);
                        $label = trim($choice['label']);
                    } else {
                        throw new RuntimeException('Stored MMW choice must contain string value and label.');
                    }
                    if ($value === '' || $label === '' || strlen($value) > 120 || strlen($label) > 120
                        || isset($choiceValues[$value])) {
                        throw new RuntimeException('Stored MMW choice is empty, too long, or duplicated.');
                    }
                    $choiceValues[$value] = true;
                    $choices[] = ['value' => $value, 'label' => $label];
                }
                $safeField['choices'] = $choices;
            }
            if ($field['type'] === 'single_choice' && !isset($safeField['choices'])) {
                throw new RuntimeException('A single_choice field must define choices.');
            }
            if (isset($safeField['min'], $safeField['max']) && $safeField['min'] > $safeField['max']) {
                throw new RuntimeException('Stored MMW field range is invalid.');
            }
            $safe[] = $safeField;
        }
        return $safe;
    }

    /** @return array<string, mixed> */
    private static function safeMedia(mixed $media): array
    {
        if (!is_array($media) || ($media !== [] && array_is_list($media))) {
            throw new RuntimeException('Stored MMW media configuration must be an object.');
        }
        $safe = [];
        if (isset($media['upload_enabled'])) {
            if (!is_bool($media['upload_enabled'])) {
                throw new RuntimeException('Stored MMW media upload flag is invalid.');
            }
            $safe['upload_enabled'] = $media['upload_enabled'];
        }
        if (isset($media['max_bytes'])) {
            if (!is_int($media['max_bytes']) || $media['max_bytes'] < 1 || $media['max_bytes'] > 25_000_000) {
                throw new RuntimeException('Stored MMW media size limit is invalid.');
            }
            $safe['max_bytes'] = $media['max_bytes'];
        }
        if (isset($media['accepted_mime_types'])) {
            $types = $media['accepted_mime_types'];
            if (!is_array($types) || !array_is_list($types) || $types === [] || count($types) > 50
                || count(array_filter($types, static fn (mixed $mime): bool => is_string($mime)
                    && preg_match('~^[a-z0-9.+-]+/[a-z0-9.+-]+$~i', $mime) === 1)) !== count($types)) {
                throw new RuntimeException('Stored MMW accepted MIME types are invalid.');
            }
            $safe['accepted_mime_types'] = array_values(array_unique($types));
        }
        if (isset($media['fields'])) {
            $safe['fields'] = self::safeFields($media['fields'], 'media');
        }
        return $safe;
    }

    private static function validateSource(mixed $source, string $kind): void
    {
        if ($source === null) {
            return;
        }
        if (!is_array($source) || ($source !== [] && array_is_list($source))) {
            throw new RuntimeException('Stored MMW source mapping must be an object.');
        }
        $collection = in_array($kind, ['gallery', 'requests', 'records', 'calendar'], true);
        if (!isset($source['type_id']) || !is_string($source['type_id'])
            || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $source['type_id'])) {
            throw new RuntimeException('Stored MMW source mapping needs a valid type_id.');
        }
        if ($kind === 'form' && (!isset($source['content_id']) || !is_string($source['content_id'])
            || !preg_match('/^[a-f0-9]{32}$/', $source['content_id']))) {
            throw new RuntimeException('A form source mapping needs a valid content_id.');
        }
        if (!$collection && $kind !== 'form') {
            throw new RuntimeException('This MMW module kind cannot map to CMS content.');
        }
        foreach (['title_field', 'subtitle_field', 'start_field', 'end_field'] as $key) {
            if (isset($source[$key]) && (!is_string($source[$key])
                || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $source[$key]))) {
                throw new RuntimeException("Stored MMW source mapping '{$key}' is invalid.");
            }
        }
    }
}

