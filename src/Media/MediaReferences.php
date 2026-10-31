<?php
declare(strict_types=1);
namespace Dendrila\Cms\Media;

final class MediaReferences
{
    /** Parse typed references, never search arbitrary JSON text. */
    public static function ids(array $definition, array $values): array
    {
        $ids = [];
        foreach ($definition['fields'] ?? [] as $field) {
            $value = $values[$field['id']] ?? null;
            if ($field['type'] === 'media') {
                foreach (is_array($value) ? $value : [$value] as $id) {
                    if (is_string($id) && $id !== '') $ids[] = $id;
                }
            }
            if ($field['type'] !== 'blocks' || !is_array($value)) continue;
            foreach ($value as $block) {
                $references = match ($block['type'] ?? '') {
                    'image' => [$block['media_id'] ?? null],
                    'gallery' => $block['media_ids'] ?? [],
                    'cards' => array_column($block['items'] ?? [], 'media_id'),
                    default => [],
                };
                foreach ($references as $id) if (is_string($id) && $id !== '') $ids[] = $id;
            }
        }
        $ids = array_values(array_unique($ids));
        sort($ids);
        return $ids;
    }

    /** Writers lock managed assets before saving, serializing against deletion. */
    public static function guardContent(\PDO $pdo, array $content, array $definition): void
    {
        if ($content['status'] === 'archived') return;
        $ids = array_unique(array_merge(self::ids($definition, $content['values']),
            self::ids($definition, $content['published_values'] ?? [])));
        sort($ids);
        foreach ($ids as $id) {
            // Existing sites may still use external/reference-kit media identifiers.
            if (!str_starts_with($id, 'm_')) continue;
            if (!$pdo->inTransaction()) throw new \LogicException('Managed media references require a content transaction.');
            $q = $pdo->prepare('SELECT state FROM media_assets WHERE site_id = ? AND media_id = ? FOR UPDATE');
            $q->execute([$content['site_id'], $id]);
            if ($q->fetchColumn() !== 'ready') {
                throw new \InvalidArgumentException('Ce média est indisponible pour ce site.');
            }
        }
    }

    /** A current locking read sees committed writers even under REPEATABLE READ. No private titles escape. */
    public static function used(\PDO $pdo, string $siteId, string $id, bool $lock = false): bool
    {
        $q = $pdo->prepare("SELECT c.values_json, c.published_values_json, t.definition_json
            FROM contents c JOIN content_types t ON t.site_id = c.site_id AND t.type_id = c.type_id
            WHERE c.site_id = ? AND c.status <> 'archived'" . ($lock ? ' FOR UPDATE' : ''));
        $q->execute([$siteId]);
        foreach ($q as $row) {
            $definition = json_decode($row['definition_json'], true, 64, JSON_THROW_ON_ERROR);
            foreach (['values_json', 'published_values_json'] as $key) {
                if ($row[$key] === null) continue;
                if (in_array($id, self::ids($definition, json_decode($row[$key], true, 64, JSON_THROW_ON_ERROR)), true)) return true;
            }
        }
        return false;
    }
}
