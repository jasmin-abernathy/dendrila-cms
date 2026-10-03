<?php
declare(strict_types=1);

namespace Dendrila\Cms\Api;

use Dendrila\Cms\Authorization\AccessDenied;
use Dendrila\Cms\Authorization\ActorContext;
use Dendrila\Cms\Authorization\AuthorizationService;
use Dendrila\Cms\Content\ContentNotFound;
use Dendrila\Cms\Content\ContentService;
use Dendrila\Cms\Storage\PdoCmsStore;
use PDO;
use RuntimeException;

final readonly class PdoSiteChangeService implements SiteChangeHandler
{
    private ContentService $content;

    public function __construct(private PDO $pdo, private ?BusinessActionExecutor $businessActions = null)
    {
        $store = new PdoCmsStore($pdo);
        $this->content = new ContentService($store, new AuthorizationService($store));
    }

    /** @param array<string, mixed> $request @return array<string, mixed> */
    public function apply(ActorContext $actor, array $request): array
    {
        $moduleId = $this->string($request, 'module_id', 64);
        $action = $this->string($request, 'action', 32);
        $requestId = $this->string($request, 'client_request_id', 128);
        $payload = $request['payload'] ?? null;
        if (!$payload instanceof \stdClass) {
            throw new \InvalidArgumentException('payload must be a JSON object.');
        }
        $payload = (array) $payload;
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $requestId)) {
            throw new \InvalidArgumentException('client_request_id must be 8–128 safe characters.');
        }

        [$module, $safeModule] = $this->module($actor->siteId, $moduleId);
        if (($module['writable'] ?? false) !== true) {
            throw new AccessDenied('This module is read-only.');
        }
        $content = match ($action) {
            'update_fields' => $this->updateForm($actor, $module, $safeModule, $payload, $requestId),
            'create_item' => $this->createGalleryItem($actor, $module, $safeModule, $payload, $requestId),
            'update_item' => $this->updateGalleryItem($actor, $module, $safeModule, $payload, $requestId),
            'delete_item' => $this->deleteGalleryItem($actor, $module, $safeModule, $payload, $requestId),
            'item_action' => $this->runBusinessAction($actor, $module, $safeModule, $payload, $requestId),
            default => throw new \InvalidArgumentException('Unknown MMW change action.'),
        };

        $message = $content['message'] ?? null;
        if (!is_string($message) || trim($message) === '') {
            $message = ($content['status'] ?? '') === 'pending_review'
                ? 'Modification envoyée pour validation.'
                : (($content['content']['status'] ?? '') === 'draft'
                    ? 'Brouillon enregistré dans le CMS.' : 'Modification appliquée.');
        }
        return [
            'status' => $content['status'] ?? 'applied',
            'request_id' => $requestId,
            'message' => $message,
            'result' => $content['content'] ?? $content['result'] ?? null,
        ];
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $safeModule @param array<string, mixed> $payload */
    private function updateForm(ActorContext $actor, array $module, array $safeModule, array $payload, string $requestId): array
    {
        if (($module['kind'] ?? null) !== 'form') {
            throw new \InvalidArgumentException('update_fields is only valid for a form module.');
        }
        $values = $this->mappedValues($module, $safeModule, $payload);
        $source = $this->source($module, 'content_id');
        return $this->content->updateFields(
            $actor, $source['content_id'], $values, $requestId, $source['type_id'],
            $this->requiresReview($actor->siteId),
        );
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $safeModule @param array<string, mixed> $payload */
    private function createGalleryItem(ActorContext $actor, array $module, array $safeModule, array $payload, string $requestId): array
    {
        if (($module['kind'] ?? null) !== 'gallery') {
            throw new \InvalidArgumentException('create_item is only valid for a gallery module.');
        }
        if (($module['options']['allow_create'] ?? false) !== true) {
            throw new AccessDenied('This gallery does not allow item creation.');
        }
        $values = $this->mappedValues($module, $safeModule, $payload);
        $source = $this->source($module, 'type_id');
        return $this->content->createDraft(
            $actor, $source['type_id'], $values, $requestId, $this->requiresReview($actor->siteId),
        );
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $safeModule @param array<string, mixed> $payload */
    private function updateGalleryItem(ActorContext $actor, array $module, array $safeModule, array $payload, string $requestId): array
    {
        if (($module['kind'] ?? null) !== 'gallery'
            || (($module['options']['allow_update'] ?? true) !== true)) {
            throw new AccessDenied('This gallery does not allow item updates.');
        }
        $itemId = $this->itemId($payload);
        $values = $this->mappedValues($module, $safeModule, $payload, ['item_id']);
        $source = $this->source($module, 'type_id');
        return $this->content->updateFields(
            $actor, $itemId, $values, $requestId, $source['type_id'], $this->requiresReview($actor->siteId),
        );
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $safeModule @param array<string, mixed> $payload */
    private function deleteGalleryItem(ActorContext $actor, array $module, array $safeModule, array $payload, string $requestId): array
    {
        if (($module['kind'] ?? null) !== 'gallery'
            || (($module['options']['allow_delete'] ?? false) !== true)) {
            throw new AccessDenied('This gallery does not allow item deletion.');
        }
        $itemId = $this->itemId($payload);
        if (array_diff(array_keys($payload), ['item_id']) !== []) {
            throw new \InvalidArgumentException('delete_item only accepts item_id.');
        }
        $source = $this->source($module, 'type_id');
        return $this->content->archive($actor, $itemId, $requestId, $source['type_id']);
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $safeModule @param array<string, mixed> $payload */
    private function runBusinessAction(ActorContext $actor, array $module, array $safeModule, array $payload, string $requestId): array
    {
        if (!in_array($module['kind'] ?? null, ['records', 'calendar'], true)) {
            throw new \InvalidArgumentException('item_action is only valid for records or calendar modules.');
        }
        $itemId = $this->string($payload, 'item_id', 128);
        $actionId = $this->string($payload, 'action_id', 64);
        $allowed = false;
        foreach ($safeModule['actions'] ?? [] as $configuredAction) {
            if ($configuredAction['id'] === $actionId) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed || array_diff(array_keys($payload), ['item_id', 'action_id']) !== []) {
            throw new AccessDenied('This business action is not configured for the module.');
        }
        if ($this->businessActions === null) {
            throw new \Dendrila\Cms\Http\HttpException(501, 'business_connector_unavailable',
                'No site business connector is configured for this action.');
        }
        return $this->businessActions->execute($actor, $module, [
            'item_id' => $itemId, 'action_id' => $actionId,
        ], $requestId);
    }

    /** @param array<string, mixed> $module @param array<string, mixed> $safeModule @param array<string, mixed> $payload @param list<string> $reserved @return array<string, mixed> */
    private function mappedValues(array $module, array $safeModule, array $payload, array $reserved = []): array
    {
        $fields = [];
        foreach ($module['fields'] ?? [] as $field) {
            if (is_array($field) && is_string($field['id'] ?? null)) {
                $fields[$field['id']] = $field;
            }
        }
        $safeIds = array_fill_keys(array_column($safeModule['fields'] ?? [], 'id'), true);
        $values = [];
        foreach ($payload as $fieldId => $value) {
            if (in_array($fieldId, $reserved, true)) {
                continue;
            }
            $field = $fields[$fieldId] ?? null;
            if (!is_array($field) || !isset($safeIds[$fieldId])
                || !is_string($field['source_field_id'] ?? null)
                || !preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field['source_field_id'])) {
                throw new \InvalidArgumentException("Field '{$fieldId}' is not mapped for this module.");
            }
            $values[$field['source_field_id']] = $value;
        }
        if ($values === []) {
            throw new \InvalidArgumentException('The change contains no mapped fields.');
        }
        return $values;
    }

    /** @return array{array<string, mixed>, array<string, mixed>} */
    private function module(string $siteId, string $moduleId): array
    {
        $query = $this->pdo->prepare('SELECT modules_json FROM site_api_settings WHERE site_id = :site_id LIMIT 1');
        $query->execute(['site_id' => $siteId]);
        $json = $query->fetchColumn();
        if (!is_string($json)) {
            throw new ContentNotFound('MMW module does not exist.');
        }
        try {
            $modules = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('Stored MMW modules are invalid JSON.', previous: $error);
        }
        if (!is_array($modules) || !array_is_list($modules)) {
            throw new RuntimeException('Stored MMW modules must be a list.');
        }
        $safeModules = PdoSiteReadModel::safeModules($modules);
        foreach ($modules as $module) {
            if (($module['id'] ?? null) !== $moduleId) {
                continue;
            }
            foreach ($safeModules as $safeModule) {
                if ($safeModule['id'] === $moduleId) {
                    return [$module, $safeModule];
                }
            }
        }
        throw new ContentNotFound('MMW module does not exist.');
    }

    /** @param array<string, mixed> $payload */
    private function itemId(array $payload): string
    {
        $id = $this->string($payload, 'item_id', 32);
        if (!preg_match('/^[a-f0-9]{32}$/', $id)) {
            throw new \InvalidArgumentException('item_id is invalid.');
        }
        return $id;
    }

    /** @param array<string, mixed> $module @return array<string, mixed> */
    private function source(array $module, string $requiredKey): array
    {
        $source = $module['source'] ?? null;
        if (!is_array($source) || !is_string($source['type_id'] ?? null)
            || !is_string($source[$requiredKey] ?? null)) {
            throw new RuntimeException('This writable module has no valid CMS source mapping.');
        }
        return $source;
    }

    private function requiresReview(string $siteId): bool
    {
        $query = $this->pdo->prepare('SELECT policy_json FROM sites WHERE site_id = :site_id LIMIT 1');
        $query->execute(['site_id' => $siteId]);
        $json = $query->fetchColumn();
        if (!is_string($json)) {
            throw new ContentNotFound('Authenticated site no longer exists.');
        }
        try {
            $policy = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new RuntimeException('Stored site policy is invalid JSON.', previous: $error);
        }
        if (!is_array($policy) || array_is_list($policy)) {
            throw new RuntimeException('Stored site policy must be an object.');
        }
        return ($policy['review_before_publish'] ?? false) === true;
    }

    /** @param array<string, mixed> $input */
    private function string(array $input, string $key, int $maxLength): string
    {
        $value = $input[$key] ?? null;
        if (!is_string($value) || trim($value) === '' || strlen($value) > $maxLength) {
            throw new \InvalidArgumentException("{$key} must be a non-empty string of at most {$maxLength} characters.");
        }
        return trim($value);
    }
}
