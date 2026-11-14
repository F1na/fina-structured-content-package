<?php

namespace Fina\StructuredContent;

use Fina\StructuredContent\BlockTypes\HeadingBlockType;
use Fina\StructuredContent\BlockTypes\ImageBlockType;
use Fina\StructuredContent\BlockTypes\QuoteBlockType;
use Fina\StructuredContent\BlockTypes\TextBlockType;
use Fina\StructuredContent\BlockTypes\VideoBlockType;
use Fina\StructuredContent\Console\Commands\ListBlockablesCommand;
use Fina\StructuredContent\Console\Commands\ListBlockTypesCommand;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BlocksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/blocks.php', 'blocks');

        $this->app->singleton(BlockTypeRegistry::class);
        $this->app->singleton(BlocksRegistrar::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'blocks-migrations');

        $this->publishes([
            __DIR__.'/../config/blocks.php' => config_path('blocks.php'),
        ], 'blocks-config');

        if (config('blocks.register_routes', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/blocks.php');
        }

        // Fail-closed by default: the package cannot presume anything about
        // the host application's roles/permissions, so every blockable is
        // inaccessible until the consuming app overrides this Gate with real
        // authorization logic (see examples/BlocksServiceProvider.php).
        //
        // The $user parameter MUST be typed as nullable: Laravel's Gate
        // silently skips a closure for guest (unauthenticated) requests
        // unless its first parameter accepts null, falling back to an
        // implicit denial either way — but only "for the right reason" if
        // we declare it explicitly here.
        Gate::define('manageBlocks', fn (?Authenticatable $user, $blockable) => false);

        $registry = $this->app->make(BlockTypeRegistry::class);
        $registry->register(TextBlockType::class);
        $registry->register(ImageBlockType::class);
        $registry->register(HeadingBlockType::class);
        $registry->register(VideoBlockType::class);
        $registry->register(QuoteBlockType::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ListBlockTypesCommand::class,
                ListBlockablesCommand::class,
            ]);
        }
    }
}
