<?php

use Fina\StructuredContent\BlocksRegistrar;
use Fina\StructuredContent\Models\Block;
use Fina\StructuredContent\StructuredContent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Models\Post;

beforeEach(function () {
    app(BlocksRegistrar::class)->registerBlockable('posts', Post::class);
    Gate::define('manageBlocks', fn (?Illuminate\Contracts\Auth\Authenticatable $user, $blockable) => true);

    $this->post = Post::create(['title' => 'Hello world']);
});

it('can be mounted under a custom prefix, independently of the default route group', function () {
    Route::prefix('custom-segment')->group(function () {
        StructuredContent::routes();
    });

    $response = $this->postJson("/custom-segment/structuredContents/posts/{$this->post->id}/blocks", [
        'type' => 'text',
        'content' => 'Hello',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('content', 'Hello');
    expect(Block::count())->toBe(1);
});

it('can be mounted more than once, once per segment', function () {
    Route::prefix('admin')->group(fn () => StructuredContent::routes());
    Route::prefix('healthFacility')->group(fn () => StructuredContent::routes());

    $this->getJson("/admin/structuredContents/posts/{$this->post->id}/blocks")->assertOk();
    $this->getJson("/healthFacility/structuredContents/posts/{$this->post->id}/blocks")->assertOk();
});

it('still registers the default route group unless disabled', function () {
    // The package's own BlocksServiceProvider auto-registers routes under
    // config('blocks.route_prefix') ("api" by default) — still reachable
    // alongside any custom groups mounted manually above.
    $this->getJson("/api/structuredContents/posts/{$this->post->id}/blocks")->assertOk();
});
