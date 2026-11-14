<?php

namespace Fina\StructuredContent\Models;

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
}
