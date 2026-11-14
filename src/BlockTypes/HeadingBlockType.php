<?php

namespace Fina\StructuredContent\BlockTypes;

use Fina\StructuredContent\AbstractBlockType;

/**
 * Generic block type registered by default by the package. Represents a
 * heading/title ("Titre"). Carries a semantic level (H1-H6) so the
 * consuming front-end can render correct heading markup instead of always
 * falling back to a single tag.
 */
class HeadingBlockType extends AbstractBlockType
{
    public static function key(): string
    {
        return 'heading';
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string'],
            'level' => ['sometimes', 'integer', 'between:1,6'],
        ];
    }

    public function toArray(array $data): array
    {
        return [
            'text' => $data['text'] ?? null,
            'level' => $data['level'] ?? 2,
        ];
    }
}
