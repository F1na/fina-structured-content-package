<?php

namespace Fina\StructuredContent\BlockTypes;

use Fina\StructuredContent\AbstractBlockType;

/**
 * Generic block type registered by default by the package. Deliberately
 * minimal (a single "content" field) so it stays reusable across projects;
 * anything more specific belongs in a consuming app's own block type.
 */
class QuoteBlockType extends AbstractBlockType
{
    public static function key(): string
    {
        return 'quote';
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string'],
        ];
    }

    public function toArray(array $data): array
    {
        return [
            'text' => $data['text'] ?? null,
        ];
    }
}
