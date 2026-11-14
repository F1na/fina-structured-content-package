<?php

use Fina\StructuredContent\StructuredContent;
use Illuminate\Support\Facades\Route;

// Default, unguarded route group auto-registered by BlocksServiceProvider.
// The actual route definitions live in StructuredContent::routes() so
// there's a single source of truth whether a project uses this default
// group or mounts the routes itself (see StructuredContent's docblock for
// the multi-guard/segmented-API case).
Route::prefix(config('blocks.route_prefix', 'api'))->group(function () {
    StructuredContent::routes();
});
