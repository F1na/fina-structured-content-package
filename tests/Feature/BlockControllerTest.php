<?php

use Fina\StructuredContent\BlocksRegistrar;
use Fina\StructuredContent\Models\Block;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\Models\Post;

beforeEach(function () {
    app(BlocksRegistrar::class)->registerBlockable('posts', Post::class);

    // Open the gate for these tests; fail-closed behavior is covered
    // separately in GateFailClosedTest. $user must stay nullable or
    // Laravel silently skips this closure for guest (unauthenticated) requests.
    Gate::define('manageBlocks', fn (?Illuminate\Contracts\Auth\Authenticatable $user, $blockable) => true);

    $this->post = Post::create(['title' => 'Hello world']);
});

it('lists blocks ordered by position', function () {
    Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'second'], 'position' => 1]);
    Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'first'], 'position' => 0]);

    $response = $this->getJson("/api/posts/{$this->post->id}/blocks");

    $response->assertOk();
    $response->assertJsonPath('0.content', 'first');
    $response->assertJsonPath('1.content', 'second');
});

it('returns 404 when the blockable type is not whitelisted', function () {
    $this->getJson('/api/unknown-type/1/blocks')->assertNotFound();
});

it('creates a block via store with position = max + 1', function () {
    $response = $this->postJson("/api/posts/{$this->post->id}/blocks", [
        'type' => 'text',
        'content' => 'Hello',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('content', 'Hello');
    $response->assertJsonPath('type', 'text');
    $response->assertJsonPath('position', 1);
    expect(Block::count())->toBe(1);
});

it('returns 422 on validation failure', function () {
    $this->postJson("/api/posts/{$this->post->id}/blocks", ['type' => 'text'])
        ->assertStatus(422);
});

it('returns 422 for an unknown block type', function () {
    $this->postJson("/api/posts/{$this->post->id}/blocks", ['type' => 'does-not-exist'])
        ->assertStatus(422);
});

it('updates a block via update', function () {
    $block = Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'old'], 'position' => 0]);

    $response = $this->putJson("/api/blocks/{$block->id}", ['content' => 'new']);

    $response->assertOk();
    $response->assertJsonPath('content', 'new');
    expect($block->fresh()->data['content'])->toBe('new');
});

it('deletes a block via destroy', function () {
    $block = Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'x'], 'position' => 0]);

    $this->deleteJson("/api/blocks/{$block->id}")->assertNoContent();

    expect(Block::find($block->id))->toBeNull();
});

it('moves a block up by swapping with its previous neighbor', function () {
    $first = Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'a'], 'position' => 0]);
    $second = Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'b'], 'position' => 1]);

    $this->postJson("/api/blocks/{$second->id}/up")->assertOk();

    expect($second->fresh()->position)->toBe(0);
    expect($first->fresh()->position)->toBe(1);
});

it('moves a block down by swapping with its next neighbor', function () {
    $first = Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'a'], 'position' => 0]);
    $second = Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'b'], 'position' => 1]);

    $this->postJson("/api/blocks/{$first->id}/down")->assertOk();

    expect($first->fresh()->position)->toBe(1);
    expect($second->fresh()->position)->toBe(0);
});

it('does nothing when moving the first block up or the last block down', function () {
    $first = Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'a'], 'position' => 0]);
    $second = Block::create(['blockableId' => $this->post->id, 'blockableType' => Post::class, 'type' => 'text', 'data' => ['content' => 'b'], 'position' => 1]);

    $this->postJson("/api/blocks/{$first->id}/up")->assertOk();
    $this->postJson("/api/blocks/{$second->id}/down")->assertOk();

    expect($first->fresh()->position)->toBe(0);
    expect($second->fresh()->position)->toBe(1);
});
