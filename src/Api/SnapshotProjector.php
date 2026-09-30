<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

/**
 * Projects CMS records into the deliberately smaller MMW v1 snapshot shape.
 * Source mappings are private CMS settings and are never copied into the output.
 */
final class SnapshotProjector
{
    /**
     * @param list<array<string, mixed>> $modules Validated stored module definitions.
     * @param callable(string, string): ?array<string, mixed> $findContent
     * @param callable(string, int): list<array<string, mixed>> $listContent
     * @return array<string, mixed>
     */
    public function project(array $modules, callable $findContent, callable $listContent): array
    {
        $data = [];
        foreach ($modules as $module) {
            $id = $module['id'];
            $kind = $module['kind'];
            $source = $module['source'] ?? [];
            if (!is_array($source) || !isset($source['type_id']) || !is_string($source['type_id'])) {
                $data[$id] = in_array($kind, ['gallery', 'requests', 'records', 'calendar'], true)
                    ? ['items' => []] : new \stdClass();
                continue;
            }

            if ($kind === 'form') {
                $contentId = $source['content_id'] ?? null;
                $content = is_string($contentId) ? $findContent($source['type_id'], $contentId) : null;
                if (is_array($content) && ($content['type_id'] ?? null) !== $source['type_id']) {
                    $content = null;
                }
                $data[$id] = is_array($content) ? $this->fieldValues($module, $content) : new \stdClass();
                continue;
            }

            if (!in_array($kind, ['gallery', 'requests', 'records', 'calendar'], true)) {
                $data[$id] = new \stdClass();
                continue;
            }

            $items = [];
            foreach ($listContent($source['type_id'], 100) as $content) {
                if (($content['status'] ?? null) === 'archived') {
                    continue;
                }
                $items[] = $this->record($module, $content, $kind);
            }
            $data[$id] = ['items' => $items];
        }
        return $data;
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $content @return array<string, mixed> */
    private function record(array $module, array $content, string $kind): array
    {
        $values = is_array($content['values'] ?? null) ? $content['values'] : [];
        $source = is_array($module['source'] ?? null) ? $module['source'] : [];
        $title = $this->sourceValue($values, $source['title_field'] ?? 'title');
        $subtitle = $this->sourceValue($values, $source['subtitle_field'] ?? null);
        $item = [
            'id' => (string) ($content['content_id'] ?? ''),
            'title' => is_string($title) && $title !== '' ? $title : (string) ($content['slug'] ?? ''),
            'subtitle' => is_string($subtitle) ? $subtitle : '',
            'status' => (string) ($content['status'] ?? ''),
        ];
        if ($kind === 'calendar') {
            foreach (['start_field' => 'start', 'end_field' => 'end'] as $setting => $protocolField) {
                $value = $this->sourceValue($values, $source[$setting] ?? null);
                if (is_string($value) && self::isCalendarValue($value)) {
                    $item[$protocolField] = $value;
                }
            }
        }
        foreach (is_array($module['fields'] ?? null) ? $module['fields'] : [] as $field) {
            $fieldId = $field['id'] ?? null;
            $sourceId = $field['source_field_id'] ?? $fieldId;
            if (!is_string($fieldId) || !is_string($sourceId) || in_array($fieldId, ['id', 'title', 'subtitle', 'status', 'start', 'end'], true)) {
                continue;
            }
            if (array_key_exists($sourceId, $values) && self::isProtocolValue($values[$sourceId])) {
                $item[$fieldId] = $values[$sourceId];
            }
        }
        return $item;
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $content @return array<string, mixed>|\stdClass */
    private function fieldValues(array $module, array $content): array|\stdClass
    {
        $values = is_array($content['values'] ?? null) ? $content['values'] : [];
        $output = [];
        foreach (is_array($module['fields'] ?? null) ? $module['fields'] : [] as $field) {
            $fieldId = $field['id'] ?? null;
            $sourceId = $field['source_field_id'] ?? $fieldId;
            if (is_string($fieldId) && is_string($sourceId) && array_key_exists($sourceId, $values)
                && self::isProtocolValue($values[$sourceId])) {
                $output[$fieldId] = $values[$sourceId];
            }
        }
        return $output === [] ? new \stdClass() : $output;
    }

    /** @param array<string, mixed> $values */
    private function sourceValue(array $values, mixed $fieldId): mixed
    {
        return is_string($fieldId) && array_key_exists($fieldId, $values) ? $values[$fieldId] : null;
    }

    private static function isProtocolValue(mixed $value): bool
    {
        return is_string($value) || is_int($value) || is_float($value) || is_bool($value);
    }

    private static function isCalendarValue(string $value): bool
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $date) === 1) {
            return checkdate((int) $date[2], (int) $date[3], (int) $date[1]);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
            return false;
        }
        try {
            new \DateTimeImmutable($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
