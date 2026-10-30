<?php
declare(strict_types=1);
namespace Dendrila\Cms\Media;

use Dendrila\Cms\Api\PdoSiteReadModel;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Content\ContentNotFound;
use Dendrila\Cms\Content\ContentTypeDefinition;
use Dendrila\Cms\Content\IdempotencyConflict;
use Dendrila\Cms\Http\HttpException;
use Dendrila\Cms\Http\UploadedFile;
use Dendrila\Cms\Storage\PdoCmsStore;
use PDO;

final readonly class MediaService
{
    private PdoCmsStore $store;
    private AuthorizationService $authorization;
    public function __construct(private PDO $pdo, private ImageStorage $storage, private string $publicEndpoint)
    {
        $url = parse_url($publicEndpoint);
        if (!is_array($url) || ($url['scheme'] ?? '') !== 'https' || empty($url['host'])
            || isset($url['user'], $url['pass']) || isset($url['user']) || isset($url['query']) || isset($url['fragment'])
            || filter_var($publicEndpoint, FILTER_VALIDATE_URL) === false) throw new \InvalidArgumentException('Controlled HTTPS media endpoint required.');
        $this->store = new PdoCmsStore($pdo);
        $this->authorization = new AuthorizationService($this->store);
    }

    public function upload(ActorContext $actor, array $fields, UploadedFile $file): array
    {
        if (array_diff(array_keys($fields), ['module_id', 'item_id', 'client_request_id', 'metadata']) !== []) throw new \InvalidArgumentException('Unknown multipart field.');
        foreach (['module_id', 'item_id', 'client_request_id', 'metadata'] as $key) {
            if (!is_string($fields[$key] ?? null) || strlen($fields[$key]) > ($key === 'metadata' ? 16000 : 128)) throw new \InvalidArgumentException('Invalid multipart fields.');
        }
        $module = $this->module($actor, $fields['module_id'], 'media.upload');
        if (($module['media']['upload_enabled'] ?? false) !== true || $fields['item_id'] !== $module['id']) throw new AccessDenied('Upload unavailable for this context.');
        $decoded = json_decode($fields['metadata'], false, 32, JSON_THROW_ON_ERROR);
        if (!$decoded instanceof \stdClass) throw new \InvalidArgumentException('metadata must be a JSON object.');
        $metadata = $this->metadata((array) $decoded, $module['media']['fields'] ?? []);
        foreach ($module['media']['fields'] ?? [] as $field) {
            if (($field['required'] ?? false) && (!isset($metadata[$field['id']]) || $metadata[$field['id']] === '')) throw new \InvalidArgumentException('Métadonnée obligatoire : ' . $field['id']);
        }
        $info = $this->storage->inspect($file);
        if ($info['bytes'] > min($this->storage->maxBytes, $module['media']['max_bytes'] ?? $this->storage->maxBytes)
            || !in_array($info['mime'], $module['media']['accepted_mime_types'] ?? ImageStorage::MIMES, true)) throw new \InvalidArgumentException('Image refusée par ce module.');
        $filename = mb_substr(basename(str_replace('\\', '/', $file->name)), 0, 255);
        if (!mb_check_encoding($filename, 'UTF-8') || preg_match('/[\x00-\x1f]/', $filename)) throw new \InvalidArgumentException('Nom de fichier invalide.');
        $hash = self::hash(['upload', $module['id'], $metadata, $filename, hash_file('sha256', $file->path)]);
        return $this->mutate($actor, $fields['client_request_id'], $hash, function () use ($actor, $module, $metadata, $info, $file, $filename): array {
            $id = 'm_' . bin2hex(random_bytes(16));
            $original = $this->storage->original($file);
            $variants = $this->storage->variants($this->storage->path($original), 0);
            $values = array_replace(['title' => $filename, 'alt' => '', 'caption' => '', 'credit' => '', 'kind' => 'normal', 'position' => 0], $metadata);
            $q = $this->pdo->prepare('INSERT INTO media_assets
                (site_id, media_id, original_key, mime, bytes, width, height, filename, metadata_json, author_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))');
            $q->execute([$actor->siteId, $id, $original, $info['mime'], $info['bytes'], $info['width'], $info['height'],
                $filename, json_encode($values, JSON_THROW_ON_ERROR), $actor->actorId]);
            $this->saveVariants($actor->siteId, $id, 1, $variants);
            return ['id' => $id, 'parent_id' => $module['id'], 'version' => 1, 'revision' => 1];
        });
    }

    public function change(ActorContext $actor, string $moduleId, string $action, array $payload, string $requestId): array
    {
        $permission = $action === 'delete_item' ? 'media.delete' : 'media.update';
        $module = $this->module($actor, $moduleId, $permission);
        if (!in_array($action, ['update_item', 'rotate_left', 'rotate_right', 'delete_item'], true)) throw new AccessDenied('Action unavailable.');
        if (str_starts_with($action, 'rotate_') && ($module['options']['allow_rotate'] ?? false) !== true) throw new AccessDenied('Rotation unavailable.');
        if ($action === 'delete_item' && ($module['options']['allow_delete'] ?? false) !== true) throw new AccessDenied('Deletion unavailable.');
        if (($payload['parent_id'] ?? null) !== $moduleId || !is_string($payload['item_id'] ?? null)
            || !preg_match('/^m_[a-f0-9]{32}$/D', $payload['item_id'])) throw new \InvalidArgumentException('Invalid media context.');
        if (array_key_exists('expected_revision', $payload)
            && (!is_int($payload['expected_revision']) || $payload['expected_revision'] < 1)) {
            throw new \InvalidArgumentException('expected_revision must be a positive integer.');
        }
        $values = array_diff_key($payload, array_flip(['item_id', 'parent_id', 'expected_revision']));
        if ($action !== 'update_item' && $values !== []) throw new \InvalidArgumentException('Unexpected action fields.');
        if ($action === 'update_item') $values = $this->metadata($values, $module['fields'] ?? []);
        return $this->mutate($actor, $requestId, self::hash([$moduleId, $action, $payload]), function () use ($actor, $action, $payload, $values): array {
            $asset = $this->asset($actor->siteId, $payload['item_id'], true);
            if (isset($payload['expected_revision']) && $payload['expected_revision'] !== (int) $asset['revision']) {
                throw new \Dendrila\Cms\Content\VersionConflict('Ce média a été modifié. Actualisez-le avant de réessayer.');
            }
            $revision = (int) $asset['revision'] + 1;
            $version = (int) $asset['version'];
            $rotation = (int) $asset['rotation'];
            $metadata = json_decode($asset['metadata_json'], true, 32, JSON_THROW_ON_ERROR);
            if ($action === 'delete_item') {
                if (MediaReferences::used($this->pdo, $actor->siteId, $asset['media_id'], true)) {
                    throw new HttpException(409, 'media_in_use', 'Ce média est encore utilisé dans un brouillon ou une publication. Retirez ses usages avant de le supprimer.');
                }
                $q = $this->pdo->prepare("UPDATE media_assets SET state = 'deleting', revision = ?, updated_at = UTC_TIMESTAMP(6) WHERE site_id = ? AND media_id = ?");
                $q->execute([$revision, $actor->siteId, $asset['media_id']]);
                // Physical deletion is a resumable maintenance step, only after this commit.
            } else {
                if ($action === 'update_item') {
                    $metadata = array_replace($metadata, $values);
                    // Retain the same derivative version on metadata-only changes.
                    $version = (int) $asset['version'];
                } else {
                    $version++;
                    $rotation = ($rotation + ($action === 'rotate_right' ? 90 : 270)) % 360;
                    $this->saveVariants($actor->siteId, $asset['media_id'], $version,
                        $this->storage->variants($this->storage->path($asset['original_key']), $rotation));
                }
                $q = $this->pdo->prepare('UPDATE media_assets SET metadata_json = ?, rotation = ?, version = ?, revision = ?, updated_at = UTC_TIMESTAMP(6) WHERE site_id = ? AND media_id = ?');
                $q->execute([json_encode($metadata, JSON_THROW_ON_ERROR), $rotation, $version, $revision, $actor->siteId, $asset['media_id']]);
            }
            return ['id' => $asset['media_id'], 'version' => $version, 'revision' => $revision, 'rotation' => $rotation];
        });
    }

    /** Returns only asset metadata to media.read, never private content usages. */
    public function items(ActorContext $actor, array $module, array $filters = []): array
    {
        return $this->search($actor, $module, $filters)['items'];
    }

    /**
     * Paginated media search for administration interfaces.
     *
     * @return array{
     *   items:list<array<string,mixed>>,
     *   total:int,
     *   limit:int,
     *   offset:int,
     *   has_more:bool,
     *   sort:string,
     *   direction:string
     * }
     */
    public function search(ActorContext $actor, array $module, array $filters = []): array
    {
        $this->authorization->require($actor, 'media.read');

        $query = $filters['query'] ?? '';
        $mime = $filters['mime'] ?? '';
        $usage = $filters['usage'] ?? 'all';
        $limit = $filters['limit'] ?? 100;
        $offset = $filters['offset'] ?? 0;
        $mediaId = $filters['id'] ?? '';
        $createdFrom = $filters['created_from'] ?? '';
        $createdTo = $filters['created_to'] ?? '';
        $minBytes = $filters['min_bytes'] ?? null;
        $maxBytes = $filters['max_bytes'] ?? null;
        $alt = $filters['alt'] ?? 'all';
        $sort = $filters['sort'] ?? 'date';
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));

        if (!is_string($query) || mb_strlen($query) > 200 || !is_string($mime)
            || !in_array($mime, ['', 'image', ...ImageStorage::MIMES], true)
            || !is_string($usage) || !in_array($usage, ['all', 'used', 'unused'], true)
            || !is_int($limit) || $limit < 1 || $limit > 100
            || !is_int($offset) || $offset < 0
            || !is_string($mediaId) || ($mediaId !== '' && preg_match('/^m_[a-f0-9]{32}$/D', $mediaId) !== 1)
            || !is_string($createdFrom) || !is_string($createdTo)
            || ($minBytes !== null && (!is_int($minBytes) || $minBytes < 0))
            || ($maxBytes !== null && (!is_int($maxBytes) || $maxBytes < 0))
            || ($minBytes !== null && $maxBytes !== null && $minBytes > $maxBytes)
            || !is_string($alt) || !in_array($alt, ['all', 'missing', 'present'], true)
            || !is_string($sort) || !in_array($sort, ['date', 'size', 'title'], true)
            || !in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Invalid media filters.');
        }
        if ($usage !== 'all') {
            $this->authorization->require($actor, 'content.read_draft');
        }

        $from = self::dateBoundary($createdFrom, false);
        $to = self::dateBoundary($createdTo, true);
        if ($from !== null && $to !== null && $from >= $to) {
            throw new \InvalidArgumentException('Invalid media date range.');
        }

        $where = ["site_id = ?", "state = 'ready'"];
        $parameters = [$actor->siteId];
        if ($mediaId !== '') {
            $where[] = 'media_id = ?';
            $parameters[] = $mediaId;
        }
        if ($mime !== '' && $mime !== 'image') {
            $where[] = 'mime = ?';
            $parameters[] = $mime;
        }
        if ($from !== null) {
            $where[] = 'created_at >= ?';
            $parameters[] = $from;
        }
        if ($to !== null) {
            $where[] = 'created_at < ?';
            $parameters[] = $to;
        }
        if ($minBytes !== null) {
            $where[] = 'bytes >= ?';
            $parameters[] = $minBytes;
        }
        if ($maxBytes !== null) {
            $where[] = 'bytes <= ?';
            $parameters[] = $maxBytes;
        }

        $orderBy = match ($sort) {
            'size' => 'bytes',
            'title' => "LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.title')), filename))",
            default => 'created_at',
        };
        $sql = 'SELECT * FROM media_assets WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $orderBy . ' ' . strtoupper($direction) . ', media_id ASC';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);

        $items = [];
        $total = 0;
        foreach ($statement as $asset) {
            $metadata = json_decode($asset['metadata_json'], true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($metadata)) {
                throw new \RuntimeException('Stored media metadata must be an object.');
            }
            $altValue = is_string($metadata['alt'] ?? null) ? trim($metadata['alt']) : '';
            if (($alt === 'missing' && $altValue !== '') || ($alt === 'present' && $altValue === '')) {
                continue;
            }
            if ($query !== '' && mb_stripos(implode(' ', [
                (string) $asset['filename'],
                (string) ($metadata['title'] ?? ''),
                (string) ($metadata['alt'] ?? ''),
                (string) ($metadata['credit'] ?? ''),
            ]), $query) === false) {
                continue;
            }
            if ($usage !== 'all'
                && MediaReferences::used($this->pdo, $actor->siteId, $asset['media_id']) !== ($usage === 'used')) {
                continue;
            }

            if ($total >= $offset && count($items) < $limit) {
                $items[] = $this->projectItem($actor->siteId, $module, $asset, $metadata);
            }
            $total++;
        }

        return [
            'items' => $items,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => $offset + count($items) < $total,
            'sort' => $sort,
            'direction' => $direction,
        ];
    }

    private function projectItem(string $siteId, array $module, array $asset, array $metadata): array
    {
        $item = [
            'id' => $asset['media_id'], 'parent_id' => $module['id'],
            'title' => $metadata['title'], 'subtitle' => $asset['mime'],
            'filename' => $asset['filename'], 'mime' => $asset['mime'], 'bytes' => (int) $asset['bytes'],
            'original_width' => (int) $asset['width'], 'original_height' => (int) $asset['height'],
            'version' => (int) $asset['version'], 'revision' => (int) $asset['revision'],
            'rotation' => (int) $asset['rotation'], 'state' => $asset['state'],
            'created_at' => str_replace(' ', 'T', $asset['created_at']) . 'Z',
            'updated_at' => str_replace(' ', 'T', $asset['updated_at']) . 'Z',
            'variants' => [],
        ];
        foreach ($module['fields'] ?? [] as $field) {
            if (array_key_exists($field['id'], $metadata)) {
                $item[$field['id']] = $metadata[$field['id']];
            }
        }
        foreach ($this->variantRows($siteId, $asset['media_id'], (int) $asset['version']) as $variant) {
            $url = $this->url($siteId, $variant['public_token']);
            $item[$variant['kind'] === 'thumb' ? 'thumb' : 'path'] = $url;
            $item['variants'][$variant['kind']] = [
                'url' => $url, 'mime' => 'image/webp',
                'width' => (int) $variant['width'], 'height' => (int) $variant['height'],
                'bytes' => (int) $variant['bytes'],
            ];
        }
        return $item;
    }

    private static function dateBoundary(string $value, bool $exclusiveEnd): ?string
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new \InvalidArgumentException('Invalid media date filter.');
        }
        if ($exclusiveEnd) {
            $date = $date->modify('+1 day');
        }
        return $date->format('Y-m-d H:i:s');
    }

    /** Public projection deliberately contains URLs only, no original keys or metadata. */
    public function publicMap(string $siteId): array
    {
        $q = $this->pdo->prepare("SELECT a.media_id, v.public_token FROM media_assets a JOIN media_variants v
            ON v.site_id = a.site_id AND v.media_id = a.media_id AND v.version = a.version
            WHERE a.site_id = ? AND a.state = 'ready' AND v.kind = 'web'");
        $q->execute([$siteId]);
        $map = [];
        foreach ($q as $row) $map[$row['media_id']] = $this->url($siteId, $row['public_token']);
        return $map;
    }

    public function publicImage(string $siteId, string $token): string
    {
        if (!preg_match('/^[a-f0-9]{48}$/D', $token) || strlen($siteId) > 64) throw new ContentNotFound('Image not found.');
        $q = $this->pdo->prepare("SELECT v.storage_key FROM media_variants v JOIN media_assets a ON a.site_id = v.site_id AND a.media_id = v.media_id
            WHERE v.site_id = ? AND v.public_token = ? AND a.state = 'ready' AND v.version = a.version");
        $q->execute([$siteId, $token]);
        $key = $q->fetchColumn();
        if (!is_string($key) || !is_file($this->storage->path($key))) throw new ContentNotFound('Image not found.');
        return $this->storage->path($key);
    }

    /** Requires the same exclusive filesystem lock as writers; safe after process crashes. */
    public function collectGarbage(): int
    {
        return $this->locked(function (): int {
            $deleted = 0;
            foreach ($this->pdo->query("SELECT site_id, media_id, original_key FROM media_assets WHERE state = 'deleting'") as $asset) {
                $q = $this->pdo->prepare('SELECT storage_key FROM media_variants WHERE site_id = ? AND media_id = ?');
                $q->execute([$asset['site_id'], $asset['media_id']]);
                $ok = true;
                foreach ([$asset['original_key'], ...$q->fetchAll(PDO::FETCH_COLUMN)] as $key) $ok = $this->storage->remove($key) && $ok;
                if ($ok) {
                    $q = $this->pdo->prepare("UPDATE media_assets SET state = 'deleted', updated_at = UTC_TIMESTAMP(6) WHERE site_id = ? AND media_id = ? AND state = 'deleting'");
                    $q->execute([$asset['site_id'], $asset['media_id']]);
                    $deleted++;
                }
            }
            $live = [];
            foreach ($this->pdo->query("SELECT original_key FROM media_assets WHERE state <> 'deleted'") as $row) $live[$row['original_key']] = true;
            foreach ($this->pdo->query("SELECT v.storage_key FROM media_variants v JOIN media_assets a ON a.site_id = v.site_id AND a.media_id = v.media_id WHERE a.state <> 'deleted'") as $row) $live[$row['storage_key']] = true;
            foreach (glob($this->storage->root . '/*') ?: [] as $path) {
                clearstatcache(true, $path);
                $key = basename($path);
                if (preg_match('/^[a-f0-9]{48}\.(original|webp)$/D', $key) && !isset($live[$key]) && filemtime($path) < time() - 3600) $this->storage->remove($key);
            }
            return $deleted;
        });
    }

    public function module(ActorContext $actor, string $id, string $permission = 'media.read'): array
    {
        $this->authorization->require($actor, 'media.read');
        $this->authorization->require($actor, $permission);
        $q = $this->pdo->prepare('SELECT modules_json FROM site_api_settings WHERE site_id = ?');
        $q->execute([$actor->siteId]);
        $modules = PdoSiteReadModel::safeModules(json_decode($q->fetchColumn() ?: '[]', true, 64, JSON_THROW_ON_ERROR));
        foreach ($modules as $module) {
            if ($module['id'] !== $id || $module['kind'] !== 'media_library') continue;
            if ($permission !== 'media.read' && ($module['writable'] ?? false) !== true) throw new AccessDenied('Read-only module.');
            return $module;
        }
        throw new ContentNotFound('Media module not found.');
    }

    private function metadata(array $values, array $fields): array
    {
        $known = array_column($fields, null, 'id');
        $limits = ['title' => 255, 'alt' => 2000, 'caption' => 2000, 'credit' => 2000, 'kind' => 80, 'position' => 100000];
        $definition = ['id' => 'media', 'label' => 'Media', 'fields' => []];
        foreach ($values as $key => $value) {
            if (!isset($known[$key], $limits[$key])) throw new \InvalidArgumentException('Métadonnée non autorisée : ' . $key);
            if ($key === 'position') {
                if (!is_int($value) || abs($value) > $limits[$key]) throw new \InvalidArgumentException('Position invalide.');
            } elseif (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limits[$key]
                || str_contains($value, "\0")) throw new \InvalidArgumentException('Métadonnée invalide : ' . $key);
            $field = $known[$key];
            if (($field['required'] ?? false) && ($value === '' || $value === null)) throw new \InvalidArgumentException('Champ obligatoire : ' . $key);
            if (is_string($value) && isset($field['max_length']) && mb_strlen($value) > $field['max_length']) throw new \InvalidArgumentException('Texte trop long : ' . $key);
            if (is_numeric($value) && $field['type'] === 'number'
                && ((isset($field['min']) && $value < $field['min']) || (isset($field['max']) && $value > $field['max']))) throw new \InvalidArgumentException('Valeur hors limites : ' . $key);
            $field['type'] = $field['type'] === 'single_choice' ? 'choice' : $field['type'];
            if ($field['type'] === 'choice') $field['options'] = array_column($field['choices'], 'value');
            $definition['fields'][] = $field;
        }
        (new ContentTypeDefinition($definition))->validateValues($values);
        return $values;
    }

    private function asset(string $siteId, string $id, bool $lock = false): array
    {
        $q = $this->pdo->prepare("SELECT * FROM media_assets WHERE site_id = ? AND media_id = ? AND state = 'ready'" . ($lock ? ' FOR UPDATE' : ''));
        $q->execute([$siteId, $id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new ContentNotFound('Média introuvable.');
        return $row;
    }
    private function saveVariants(string $siteId, string $id, int $version, array $variants): void
    {
        $q = $this->pdo->prepare('INSERT INTO media_variants (site_id, media_id, version, kind, storage_key, public_token, width, height, bytes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
        foreach ($variants as $kind => $v) $q->execute([$siteId, $id, $version, $kind, $v['storage_key'], $v['public_token'], $v['width'], $v['height'], $v['bytes']]);
    }
    private function variantRows(string $siteId, string $id, int $version): array
    {
        $q = $this->pdo->prepare('SELECT * FROM media_variants WHERE site_id = ? AND media_id = ? AND version = ?');
        $q->execute([$siteId, $id, $version]);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }
    private function url(string $siteId, string $token): string
    {
        return $this->publicEndpoint . '?site=' . rawurlencode($siteId) . '&token=' . $token;
    }
    private function mutate(ActorContext $actor, string $requestId, string $hash, callable $operation): array
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/D', $requestId)) throw new \InvalidArgumentException('Invalid client_request_id.');
        return $this->locked(fn (): array => $this->store->transaction(function () use ($actor, $requestId, $hash, $operation): array {
            $old = $this->store->findIdempotency($actor->siteId, $actor->actorId, $requestId);
            if ($old !== null) {
                if (!hash_equals($old['request_hash'], $hash) || !is_array($old['response'])) throw new IdempotencyConflict('Identifiant déjà utilisé pour une autre opération.');
                return $old['response'];
            }
            if (!$this->store->claimIdempotency($actor->siteId, $actor->actorId, $requestId, $hash)) throw new IdempotencyConflict('Opération déjà en cours.');
            $result = $operation();
            $result['site_revision'] = $this->store->incrementDataRevision($actor->siteId);
            $response = ['status' => 'applied', 'request_id' => $requestId, 'message' => 'Médiathèque mise à jour.', 'result' => $result];
            $this->store->appendAudit($actor->siteId, $actor->actorId, $actor->deviceId, 'media.changed', 'media', $result['id'], 'allowed', []);
            $this->store->completeIdempotency($actor->siteId, $actor->actorId, $requestId, json_encode($response, JSON_THROW_ON_ERROR));
            return $response;
        }));
    }
    private function locked(callable $operation): mixed
    {
        $handle = fopen($this->storage->root . '/.media.lock', 'c');
        if (!$handle || !flock($handle, LOCK_EX)) throw new \RuntimeException('Media storage lock unavailable.');
        try { return $operation(); } finally { flock($handle, LOCK_UN); fclose($handle); }
    }
    private static function hash(mixed $value): string
    {
        $normalize = static function (mixed $v) use (&$normalize): mixed {
            if (is_array($v)) { if (!array_is_list($v)) ksort($v); foreach ($v as &$child) $child = $normalize($child); }
            return $v;
        };
        return hash('sha256', json_encode($normalize($value), JSON_THROW_ON_ERROR));
    }
}
