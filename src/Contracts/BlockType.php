<?php

namespace Fina\StructuredContent\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Contract implemented by every block type. Concrete block types are always
 * defined by the consuming application (or by the package's own generic
 * "text"/"image" defaults) — never by the package's infrastructure code,
 * which only ever talks to this interface via BlockTypeRegistry.
 */
interface BlockType
{
    /**
     * The unique key used to reference this block type (stored in blocks.type).
     */
    public static function key(): string;

    /**
     * Validation rules applied to the block's payload on store.
     */
    public function rules(): array;

    /**
     * Validation rules applied on update. Defaults to rules() (see
     * AbstractBlockType) — override when a field that's required on create
     * should stay optional on update (e.g. a file that isn't being replaced).
     */
    public function rulesForUpdate(): array;

    /**
     * Transforms the validated payload into what actually gets persisted in
     * the block's "data" column. This is where a block type that owns a
     * file (no shared media library — see ImageBlockType) moves an uploaded
     * file to storage and swaps it for the resulting path/metadata, since a
     * raw UploadedFile can't be JSON-encoded. $previousData is the block's
     * current data on update (empty array on create), so the block type can
     * decide whether to keep or replace/delete an existing file.
     *
     * Defaults to returning $validated unchanged (see AbstractBlockType) for
     * block types with no external resource to manage.
     */
    public function prepareForStorage(array $validated, Request $request, array $previousData): array;

    /**
     * Called right before a block is deleted, so a block type that owns an
     * external resource (a stored file) can clean it up. Defaults to a
     * no-op (see AbstractBlockType).
     */
    public function afterDelete(array $data): void;

    /**
     * Shape the persisted data into the array returned by the API.
     */
    public function toArray(array $data): array;

    /**
     * Fine-grained authorization for this specific block type, independent of
     * the general "manageBlocks" Gate. Defaults to true (see AbstractBlockType)
     * so consuming apps only need to override it for block types that must be
     * restricted further (e.g. an admin-only block).
     */
    public function authorizeSave(?Authenticatable $user, $blockable): bool;
}
