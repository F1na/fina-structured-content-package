<?php

namespace Fina\StructuredContent;

use Fina\StructuredContent\Contracts\BlockType;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Base class for block types. Consuming applications should extend this
 * rather than implementing BlockType directly, so they only need to
 * override the hooks relevant to them: authorizeSave() for per-type
 * restrictions, or rulesForUpdate()/prepareForStorage()/afterDelete() for a
 * block type that owns an external resource such as an uploaded file.
 */
abstract class AbstractBlockType implements BlockType
{
    public function authorizeSave(?Authenticatable $user, $blockable): bool
    {
        return true;
    }

    public function rulesForUpdate(): array
    {
        return $this->rules();
    }

    public function prepareForStorage(array $validated, Request $request, array $previousData): array
    {
        return $validated;
    }

    public function afterDelete(array $data): void
    {
        //
    }
}
