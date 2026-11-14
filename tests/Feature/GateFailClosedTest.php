<?php

use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\Models\Post;

it('denies the manageBlocks gate by default (fail-closed)', function () {
    $post = Post::create(['title' => 'Hello']);

    expect(Gate::denies('manageBlocks', $post))->toBeTrue();
});

it('returns 403 on every route when the gate is not overridden', function () {
    app(Fina\StructuredContent\BlocksRegistrar::class)->registerBlockable('posts', Post::class);
    $post = Post::create(['title' => 'Hello']);

    $this->getJson("/api/structuredContents/posts/{$post->id}/blocks")->assertForbidden();
    $this->postJson("/api/structuredContents/posts/{$post->id}/blocks", ['type' => 'text', 'content' => 'x'])->assertForbidden();
});
