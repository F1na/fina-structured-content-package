<?php

namespace Workbench\App\Providers;

use Fina\StructuredContent\BlocksRegistrar;
use Fina\StructuredContent\BlockTypeRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Blocks\CalloutBlockType;
use Workbench\App\Models\Post;

/**
 * Stands in for the "application-level provider" every real consumer of
 * fina/structured-content writes for itself (see
 * backend/examples/BlocksServiceProvider.php): whitelists a blockable,
 * registers a custom block type, and overrides the package's fail-closed
 * Gate. Must run after Fina\StructuredContent\BlocksServiceProvider — see
 * ordering in ../../testbench.yaml.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function boot(BlocksRegistrar $blockables, BlockTypeRegistry $blockTypes): void
    {
        $blockables->registerBlockable('posts', Post::class);

        $blockTypes->register(CalloutBlockType::class);

        // Wide open on purpose: this is a local sandbox, not a template for
        // a real host app's authorization logic.
        Gate::define('manageBlocks', fn (?Authenticatable $user, $blockable) => true);
    }
}
