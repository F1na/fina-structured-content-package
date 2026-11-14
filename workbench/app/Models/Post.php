<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Sandbox blockable used only to exercise the package through Workbench —
 * mirrors tests/Fixtures/Models/Post.php, kept separate since the two run in
 * different bootstraps (Pest vs. `testbench serve`).
 */
class Post extends Model
{
    const CREATED_AT = 'createdAt';

    const UPDATED_AT = 'updatedAt';

    protected $table = 'post';

    protected $guarded = [];
}
