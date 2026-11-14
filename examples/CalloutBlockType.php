<?php

/**
 * Example custom block type, defined by the consuming application. Not part
 * of the package — shown here purely for documentation.
 *
 * Illustrates authorizeSave() being used to restrict a single block type
 * (here: to admins only) independently of the general "manageBlocks" Gate,
 * which only checks whether the user may edit the blockable at all.
 */

namespace App\Blocks;

use Fina\StructuredContent\AbstractBlockType;
use Illuminate\Contracts\Auth\Authenticatable;

class CalloutBlockType extends AbstractBlockType
{
    public static function key(): string
    {
        return 'callout';
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string'],
            'style' => ['required', 'in:info,warning,danger'],
        ];
    }

    public function toArray(array $data): array
    {
        return [
            'title' => $data['title'],
            'body' => $data['body'],
            'style' => $data['style'],
        ];
    }

    public function authorizeSave(?Authenticatable $user, $blockable): bool
    {
        return $user !== null && $user->hasRole('admin');
    }
}
