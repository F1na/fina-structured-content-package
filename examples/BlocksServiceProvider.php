<?php

/**
 * Example of an APPLICATION-level provider, illustrating how a project
 * consuming fina/structured-content wires up its own blockables, custom block types, and
 * real authorization logic. This file lives in the package repo's
 * examples/ directory for documentation purposes only — it is NOT
 * autoloaded or published by the package.
 *
 * IMPORTANT: this provider MUST be declared AFTER
 * Fina\StructuredContent\BlocksServiceProvider in bootstrap/providers.php:
 *
 *     return [
 *         // ...
 *         Fina\StructuredContent\BlocksServiceProvider::class, // added by Composer package auto-discovery
 *         App\Providers\BlocksServiceProvider::class, // this file, listed explicitly, AFTER
 *     ];
 *
 * Composer's package auto-discovery loads the package provider before any
 * explicitly listed application provider. Since the package's boot() defines
 * a fail-closed "manageBlocks" Gate and registers only its own generic block
 * types, this provider's boot() must run afterwards to override the Gate and
 * populate the registries — otherwise the app's overrides would be lost.
 */

namespace App\Providers;

use Fina\StructuredContent\BlocksRegistrar;
use Fina\StructuredContent\BlockTypeRegistry;
use App\Blocks\CalloutBlockType;
use App\Models\Article;
use App\Models\Page;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BlocksServiceProviderTest extends ServiceProvider
{
    public function boot(BlocksRegistrar $blockables, BlockTypeRegistry $blockTypes): void
    {
        // 1. Whitelist which of this app's models may hold blocks, and under
        //    which route key.
        $blockables->registerBlockable('articles', Article::class);
        $blockables->registerBlockable('pages', Page::class);

        // 2. Register this app's own custom block types, in addition to the
        //    package's built-in "text"/"image" types.
        $blockTypes->register(CalloutBlockType::class);

        // 3. Override the package's fail-closed Gate with real, app-specific
        //    authorization logic. The package intentionally has no opinion
        //    on this — it's entirely up to the host application.
        //
        //    $user MUST stay nullable (?Authenticatable): Laravel's Gate
        //    silently skips a closure for guest requests unless its first
        //    parameter accepts null, which would make this override look
        //    like it "isn't working" for unauthenticated calls.
        Gate::define('manageBlocks', function (?Authenticatable $user, $blockable) {
            return match (true) {
                $blockable instanceof Article => $user !== null && $user->can('update', $blockable),
                $blockable instanceof Page => $user !== null && $user->hasRole('editor'),
                default => false,
            };
        });
    }
}
