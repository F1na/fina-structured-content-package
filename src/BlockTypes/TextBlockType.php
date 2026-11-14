<?php

namespace Fina\StructuredContent\BlockTypes;

use Fina\StructuredContent\AbstractBlockType;

/**
 * Generic block type registered by default by the package. Deliberately
 * minimal (a single "content" field) so it stays reusable across projects;
 * anything more specific belongs in a consuming app's own block type.
 */
class TextBlockType extends AbstractBlockType
{
    public static function key(): string
    {
        return 'text';
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
        ];
    }

    public function toArray(array $data): array
    {
        return [
            'content' => $data['content'] ?? null,
        ];
    }
}
