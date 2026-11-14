<?php

namespace Fina\StructuredContent\Models;

use Fina\StructuredContent\BlockTypeRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Block extends Model
{
    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'block';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'position' => 'integer',
    ];

    public function blockable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'blockableType', 'blockableId');
    }

    /**
     * Serializing a block (toArray()/toJson()/API resources) yields the same
     * shape as the HTTP API. Raw attributes stay available via ->data etc.
     */
    public function toArray(): array
    {
        return $this->toStructuredArray();
    }

    /**
     * The block as returned by the API: id/type/position plus whatever the
     * block type's toArray() makes of the persisted data.
     */
    public function toStructuredArray(): array
    {
        $type = app(BlockTypeRegistry::class)->resolve($this->type);

        return array_merge(
            [
                'id' => $this->id,
                'type' => $this->type,
                'position' => $this->position,
            ],
            $type->toArray($this->data)
        );
    }
}
