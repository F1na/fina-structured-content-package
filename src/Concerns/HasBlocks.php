<?php

namespace Fina\StructuredContent\Concerns;

use Fina\StructuredContent\BlockTypeRegistry;
use Fina\StructuredContent\Models\Block;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a blockable model a `blocks` relation, so blocks can be read (and
 * eager-loaded) without going through the HTTP API:
 *
 *     class Article extends Model { use HasBlocks; }
 *
 *     Article::with('blocks')->get();
 *     $article->blocks;
 *     $article->blocks->toArray(); // same shape as the API
 *
 * Using this trait is optional: the HTTP layer only relies on the whitelist
 * held by BlocksRegistrar.
 */
trait HasBlocks
{
    public function blocks(): MorphMany
    {
        return $this->morphMany(Block::class, 'blockable', 'blockableType', 'blockableId')
            ->orderBy('position');
    }

    protected static function bootHasBlocks(): void
    {
        // There is no database-level FK on a polymorphic relation, so blocks
        // (and the files their block types own) are cleaned up here.
        static::deleting(function ($model) {
            // Soft delete: keep the blocks so a restore brings them back.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            $registry = app(BlockTypeRegistry::class);

            $model->blocks()->get()->each(function (Block $block) use ($registry) {
                $registry->resolve($block->type)->afterDelete($block->data);
                $block->delete();
            });
        });
    }
}
