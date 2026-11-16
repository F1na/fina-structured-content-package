<?php

namespace Fina\StructuredContent;

use Fina\StructuredContent\Http\Controllers\BlockController;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

/**
 * Alternative to the package's auto-registered routes (routes/blocks.php),
 * for projects whose API is segmented by guard (e.g. /admin, /healthFacility)
 * and therefore need these routes mounted more than once, each behind a
 * different prefix/auth middleware — something a single fixed route group
 * loaded by BlocksServiceProvider can't express.
 *
 * Call this from within your own route group, e.g.:
 *
 *     Route::prefix('admin')->middleware('auth:admin')->group(function () {
 *         StructuredContent::routes();
 *     });
 *
 *     Route::prefix('healthFacility')->middleware('auth:healthFacility')->group(function () {
 *         StructuredContent::routes();
 *     });
 *
 * All routes are additionally prefixed with `structuredContents/`
 * (e.g. /admin/structuredContents/posts/1/blocks).
 *
 * Set config('blocks.register_routes') to false to stop the package from
 * also auto-registering its own default (unguarded) route group, so the
 * routes aren't registered twice.
 */
class StructuredContent
{
    public static function routes(): void
    {
        Route::prefix('structuredContents')->middleware(SubstituteBindings::class)->group(function () {
            Route::get('/{blockableType}/{blockableId}/blocks', [BlockController::class, 'index']);
            Route::post('/{blockableType}/{blockableId}/blocks', [BlockController::class, 'store']);

            Route::post('/blocks/{block}/up', [BlockController::class, 'up']);
            Route::post('/blocks/{block}/down', [BlockController::class, 'down']);
            Route::post('/blocks/{block}', [BlockController::class, 'update']);
            Route::delete('/blocks/{block}', [BlockController::class, 'destroy']);
        });
    }
}
