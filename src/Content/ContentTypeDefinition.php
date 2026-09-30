<?php
declare(strict_types=1);

namespace Dendrila\Cms\Content;

final readonly class ContentTypeDefinition
{
    private const FIELD_TYPES = [
        'text', 'multiline', 'rich_text', 'boolean', 'number', 'url', 'email',
        'choice', 'media', 'relation', 'blocks',
    ];

    /** @param array<string, mixed> $definition */
    public function __construct(private array $definition)
    {
        if (!isset($definition['id'], $definition['label'], $definition['fields'])
            || !is_string($definition['id'])
            || !preg_match('/^[a-z][a-z0-9_-]*$/', $definition['id'])
            || !is_string($definition['label']) || trim($definition['label']) === ''
            || !is_array($definition['fields'])) {
            throw new \InvalidArgumentException('Invalid content type definition.');
        }

        $ids = [];
        foreach ($definition['fields'] as $field) {
            if (!is_array($field) || !isset($field['id'], $field['type'], $field['label'])
                || !is_string($field['id']) || !preg_match('/^[a-z][a-z0-9_]*$/', $field['id'])
                || !is_string($field['type']) || !in_array($field['type'], self::FIELD_TYPES, true)
                || !is_string($field['label']) || isset($ids[$field['id']])) {
                throw new \InvalidArgumentException('Invalid or duplicate field definition.');
            }
            foreach (['required', 'multiple'] as $booleanOption) {
                if (isset($field[$booleanOption]) && !is_bool($field[$booleanOption])) {
                    throw new \InvalidArgumentException("Field option '{$booleanOption}' must be boolean.");
                }
            }
            if ($field['type'] === 'choice'
                && (!is_array($field['options'] ?? null) || !array_is_list($field['options'])
                    || $field['options'] === [] || count(array_filter($field['options'], 'is_string')) !== count($field['options']))) {
                throw new \InvalidArgumentException("Choice field '{$field['id']}' needs a list of string options.");
            }
            $ids[$field['id']] = true;
        }

        if (isset($definition['workflow']) && !in_array($definition['workflow'], ['editorial', 'direct'], true)) {
            throw new \InvalidArgumentException('Unsupported content workflow.');
        }
    }

    public function id(): string
    {
        return $this->definition['id'];
    }

    /** @param array<string, mixed> $values */
    public function validateValues(array $values): void
    {
        $known = [];
        foreach ($this->definition['fields'] as $field) {
            $id = $field['id'];
            $known[$id] = true;
            $present = array_key_exists($id, $values);
            if (($field['required'] ?? false) && (!$present || $values[$id] === null || $values[$id] === '')) {
                throw new \InvalidArgumentException("Required field '{$id}' is missing.");
            }
            if (!$present || $values[$id] === null) {
                continue;
            }
            $value = $values[$id];
            $valid = match ($field['type']) {
                'text', 'multiline', 'rich_text' => is_string($value) && strlen($value) <= 100000,
                'boolean' => is_bool($value),
                'number' => is_int($value) || is_float($value),
                'url' => $this->safeUrl($value),
                'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
                'choice' => $this->validChoice($field, $value),
                'media', 'relation' => $this->validReference($field, $value),
                'blocks' => $this->validBlocks($value),
                default => false,
            };
            if (!$valid) {
                throw new \InvalidArgumentException("Value for field '{$id}' does not match its declared type.");
            }
        }

        foreach ($values as $key => $_) {
            if (!is_string($key) || !isset($known[$key])) {
                throw new \InvalidArgumentException('Values contain an undeclared field.');
            }
        }
    }

    /** @param array<string, mixed> $field */
    private function validChoice(array $field, mixed $value): bool
    {
        $options = $field['options'] ?? [];
        if (($field['multiple'] ?? false) === true) {
            return is_array($value) && array_is_list($value)
                && count(array_diff($value, $options)) === 0
                && count(array_filter($value, 'is_string')) === count($value);
        }
        return is_string($value) && in_array($value, $options, true);
    }

    /** @param array<string, mixed> $field */
    private function validReference(array $field, mixed $value): bool
    {
        $validOne = static fn (mixed $item): bool => is_string($item)
            && preg_match('/^[A-Za-z0-9_-]{1,128}$/', $item) === 1;
        if (($field['multiple'] ?? false) === true) {
            return is_array($value) && array_is_list($value)
                && count(array_filter($value, $validOne)) === count($value);
        }
        return $validOne($value);
    }

    private function validBlocks(mixed $value): bool
    {
        if (!is_array($value) || !array_is_list($value) || count($value) > 200) {
            return false;
        }
        $allowed = [
            'text' => ['type', 'text'],
            'rich_text' => ['type', 'text'],
            'image' => ['type', 'media_id', 'alt', 'caption'],
            'gallery' => ['type', 'media_ids', 'alt'],
            'cta' => ['type', 'label', 'url'],
            'cards' => ['type', 'items'],
            'faq' => ['type', 'items'],
            'key_values' => ['type', 'items'],
            'embed_safe' => ['type', 'provider', 'url', 'label'],
        ];
        foreach ($value as $block) {
            if (!is_array($block) || !isset($block['type']) || !is_string($block['type'])
                || !isset($allowed[$block['type']]) || array_diff(array_keys($block), $allowed[$block['type']]) !== []) {
                return false;
            }
            $type = $block['type'];
            if (in_array($type, ['text', 'rich_text'], true)
                && (!is_string($block['text'] ?? null) || strlen($block['text']) > 100000)) {
                return false;
            }
            if ($type === 'image' && !$this->validReference(['multiple' => false], $block['media_id'] ?? null)) {
                return false;
            }
            if ($type === 'image' && ((!$this->optionalText($block, 'alt')) || (!$this->optionalText($block, 'caption')))) {
                return false;
            }
            if ($type === 'gallery' && !$this->validReference(['multiple' => true], $block['media_ids'] ?? null)) {
                return false;
            }
            if ($type === 'gallery' && !$this->optionalText($block, 'alt')) {
                return false;
            }
            if ($type === 'cta' && (!is_string($block['label'] ?? null) || !$this->safeUrl($block['url'] ?? null))) {
                return false;
            }
            if ($type === 'cta' && strlen($block['label']) > 300) {
                return false;
            }
            if (in_array($type, ['cards', 'faq', 'key_values'], true)
                && (!is_array($block['items'] ?? null) || !array_is_list($block['items']) || count($block['items']) > 50)) {
                return false;
            }
            if (in_array($type, ['cards', 'faq', 'key_values'], true)
                && !$this->validBlockItems($type, $block['items'])) {
                return false;
            }
            if ($type === 'embed_safe' && !$this->validEmbed($block)) {
                return false;
            }
        }
        return true;
    }

    private function validBlockItems(string $type, array $items): bool
    {
        [$required, $allowed] = match ($type) {
            'cards' => [['title'], ['title', 'text', 'url', 'media_id']],
            'faq' => [['question', 'answer'], ['question', 'answer']],
            'key_values' => [['label', 'value'], ['label', 'value']],
        };
        foreach ($items as $item) {
            if (!is_array($item) || array_diff(array_keys($item), $allowed) !== []
                || array_diff($required, array_keys($item)) !== []) {
                return false;
            }
            foreach ($item as $key => $value) {
                if (!is_string($value) || strlen($value) > 100000) {
                    return false;
                }
                if ($key === 'url' && !$this->safeUrl($value)) {
                    return false;
                }
                if ($key === 'media_id' && !$this->validReference(['multiple' => false], $value)) {
                    return false;
                }
            }
        }
        return true;
    }

    /** @param array<string, mixed> $block */
    private function validEmbed(array $block): bool
    {
        if (!is_string($block['provider'] ?? null) || !is_string($block['url'] ?? null)
            || !$this->safeUrl($block['url']) || !$this->optionalText($block, 'label')) {
            return false;
        }
        $host = strtolower((string) parse_url($block['url'], PHP_URL_HOST));
        $hosts = [
            'youtube-nocookie' => ['www.youtube-nocookie.com'],
            'vimeo' => ['player.vimeo.com'],
            'openstreetmap' => ['www.openstreetmap.org'],
        ];
        return in_array($host, $hosts[$block['provider']] ?? [], true);
    }

    /** @param array<string, mixed> $value */
    private function optionalText(array $value, string $key): bool
    {
        return !array_key_exists($key, $value)
            || (is_string($value[$key]) && strlen($value[$key]) <= 2000);
    }

    private function safeUrl(mixed $value): bool
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return in_array($scheme, ['https', 'http'], true);
    }
}
