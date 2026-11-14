<?php

namespace Tests\Fixtures\Models;

use Fina\StructuredContent\Concerns\HasBlocks;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal blockable model used only by this package's test suite, standing
 * in for a real project's Article/Page/etc.
 */
class Post extends Model
{
    use HasBlocks;

    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'post';

    protected $guarded = [];
}
