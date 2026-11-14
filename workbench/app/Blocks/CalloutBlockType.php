<?php

namespace Workbench\App\Blocks;

use Fina\StructuredContent\AbstractBlockType;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Sandbox-only custom block type, copied from examples/CalloutBlockType.php,
 * so Workbench exercises the extension points (a type beyond the package's
 * built-in text/image/heading) and not just the defaults.
 */
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
        return true;
    }
}
