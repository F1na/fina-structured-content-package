<?php

use Fina\StructuredContent\BlocksRegistrar;
use Illuminate\Support\Facades\Gate;
use Tests\Fixtures\Models\Post;

beforeEach(function () {
    app(BlocksRegistrar::class)->registerBlockable('posts', Post::class);
    Gate::define('manageBlocks', fn (?Illuminate\Contracts\Auth\Authenticatable $user, $blockable) => true);

    $this->post = Post::create(['title' => 'Hello world']);
});

it('creates a heading block with an explicit level', function () {
    $response = $this->postJson("/api/structuredContents/posts/{$this->post->id}/blocks", [
        'type' => 'heading',
        'text' => 'Section title',
        'level' => 1,
    ]);

    $response->assertCreated();
    $response->assertJsonPath('type', 'heading');
    $response->assertJsonPath('text', 'Section title');
    $response->assertJsonPath('level', 1);
});

it('defaults the heading level to 2 when omitted', function () {
    $response = $this->postJson("/api/structuredContents/posts/{$this->post->id}/blocks", [
        'type' => 'heading',
        'text' => 'Section title',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('level', 2);
});

it('rejects a heading without content', function () {
    $this->postJson("/api/structuredContents/posts/{$this->post->id}/blocks", [
        'type' => 'heading',
        'level' => 3,
    ])->assertStatus(422);
});

it('rejects a heading level out of the 1-6 range', function () {
    $this->postJson("/api/structuredContents/posts/{$this->post->id}/blocks", [
        'type' => 'heading',
        'text' => 'Section title',
        'level' => 7,
    ])->assertStatus(422);
});
