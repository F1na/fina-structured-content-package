<?php

namespace Tests\Fixtures\BlockTypes;

use Fina\StructuredContent\AbstractBlockType;

/**
 * Minimal fixture block type standing in for a consuming app's own custom
 * block type, used only to verify that BlockTypeRegistry/ListBlockTypesCommand
 * pick up types registered outside the package.
 */
class FakeCustomBlockType extends AbstractBlockType
{
    public static function key(): string
    {
        return 'custom';
    }

    public function rules(): array
    {
        return [
            'value' => ['required', 'string'],
        ];
    }

    public function toArray(array $data): array
    {
        return [
            'value' => $data['value'] ?? null,
        ];
    }
}
