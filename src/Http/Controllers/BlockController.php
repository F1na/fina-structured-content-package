<?php

namespace Fina\StructuredContent\Http\Controllers;

use Fina\StructuredContent\BlocksRegistrar;
use Fina\StructuredContent\BlockTypeRegistry;
use Fina\StructuredContent\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Fully generic controller: it never references a concrete block type or a
 * concrete blockable model. Everything domain-specific is delegated to
 * BlockTypeRegistry (which block type implementation to use) and
 * BlocksRegistrar (which Eloquent class a route segment maps to), both of
 * which are populated exclusively by the consuming application.
 */
class BlockController extends Controller
{
    public function __construct(
        protected BlockTypeRegistry $blockTypes,
        protected BlocksRegistrar $blockables,
    ) {
    }

    public function index(string $blockableType, int $blockableId)
    {
        $blockable = $this->resolveBlockable($blockableType, $blockableId);

        if (Gate::denies('manageBlocks', $blockable)) {
            abort(403);
        }

        $blocks = Block::query()
            ->where('blockableType', $blockable::class)
            ->where('blockableId', $blockable->getKey())
            ->orderBy('position')
            ->get();

        return response()->json(
            $blocks->map(fn (Block $block) => $this->serialize($block))->values()
        );
    }

    public function store(Request $request, string $blockableType, int $blockableId)
    {
        $blockable = $this->resolveBlockable($blockableType, $blockableId);

        // Gate checked before resolving the block type / running validation,
        // so an unauthorized request never pays for that extra work.
        if (Gate::denies('manageBlocks', $blockable)) {
            abort(403);
        }

        Log::debug("Keke");
        Log::debug("Type : " . (string) json_encode($request->all()));

        $type = $this->blockTypes->resolve((string) $request->input('type'));

        if (! $type->authorizeSave($request->user(), $blockable)) {
            abort(403);
        }

        $data = $request->validate($type->rules());
        $data = $type->prepareForStorage($data, $request, []);

        $position = (int) Block::query()
            ->where('blockableType', $blockable::class)
            ->where('blockableId', $blockable->getKey())
            ->max('position');

        $block = Block::create([
            'blockableId' => $blockable->getKey(),
            'blockableType' => $blockable::class,
            'type' => $type::key(),
            'data' => $data,
            'position' => $position + 1,
        ]);

        return response()->json($this->serialize($block), 201);
    }

    public function update(Request $request, Block $block)
    {
        $blockable = $block->blockable;

        if (Gate::denies('manageBlocks', $blockable)) {
            abort(403);
        }

        $type = $this->blockTypes->resolve($block->type);

        if (! $type->authorizeSave($request->user(), $blockable)) {
            abort(403);
        }

        $data = $request->validate($type->rulesForUpdate());
        $data = $type->prepareForStorage($data, $request, $block->data);

        $block->update(['data' => $data]);

        return response()->json($this->serialize($block));
    }

    public function destroy(Block $block)
    {
        $blockable = $block->blockable;

        if (Gate::denies('manageBlocks', $blockable)) {
            abort(403);
        }

        $this->blockTypes->resolve($block->type)->afterDelete($block->data);

        DB::transaction(function () use ($block) {
            $block->delete();

            // Close the gap: shift every following block up by one position.
            Block::query()
                ->where('blockableType', $block->blockableType)
                ->where('blockableId', $block->blockableId)
                ->where('position', '>', $block->position)
                ->decrement('position');
        });

        return response()->json(null, 204);
    }

    /**
     * Moves the block one step towards the start of its blockable's list.
     * No-op when it is already first.
     */
    public function up(Block $block)
    {
        return $this->move($block, 'up');
    }

    /**
     * Moves the block one step towards the end of its blockable's list.
     * No-op when it is already last.
     */
    public function down(Block $block)
    {
        return $this->move($block, 'down');
    }

    private function move(Block $block, string $direction)
    {
        $blockable = $block->blockable;

        if (Gate::denies('manageBlocks', $blockable)) {
            abort(403);
        }

        $up = $direction === 'up';

        $neighbor = Block::query()
            ->where('blockableType', $block->blockableType)
            ->where('blockableId', $block->blockableId)
            ->where('position', $up ? '<' : '>', $block->position)
            ->orderBy('position', $up ? 'desc' : 'asc')
            ->first();

        if ($neighbor !== null) {
            DB::transaction(function () use ($block, $neighbor) {
                $position = $block->position;

                $block->update(['position' => $neighbor->position]);
                $neighbor->update(['position' => $position]);
            });
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Resolves a route's {blockableType}/{blockableId} pair into an actual
     * model instance, going only through the whitelist held by
     * BlocksRegistrar. A type that isn't whitelisted is treated as a missing
     * route resource (404), never as arbitrary class instantiation.
     */
    private function resolveBlockable(string $type, int $id)
    {
        $modelClass = $this->blockables->resolveBlockableClass($type);

        if ($modelClass === null) {
            abort(404);
        }

        return $modelClass::findOrFail($id);
    }

    private function serialize(Block $block): array
    {
        $type = $this->blockTypes->resolve($block->type);

        return array_merge(
            [
                'id' => $block->id,
                'type' => $block->type,
                'position' => $block->position,
            ],
            $type->toArray($block->data)
        );
    }
}
