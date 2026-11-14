<?php

use Fina\StructuredContent\BlocksRegistrar;
use Fina\StructuredContent\Models\Block;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\Models\Post;

beforeEach(function () {
    Storage::fake('public');

    app(BlocksRegistrar::class)->registerBlockable('posts', Post::class);
    Gate::define('manageBlocks', fn (?Illuminate\Contracts\Auth\Authenticatable $user, $blockable) => true);

    $this->post = Post::create(['title' => 'Hello world']);
});

it('creates an image block by uploading a file directly', function () {
    $file = UploadedFile::fake()->image('photo.jpg', 100, 100);

    $response = $this->post("/api/structuredContents/posts/{$this->post->id}/blocks", [
        'type' => 'image',
        'file' => $file,
        'alt' => 'A photo',
    ]);

    $response->assertCreated();
    $response->assertJsonPath('type', 'image');
    $response->assertJsonPath('alt', 'A photo');
    expect($response->json('url'))->not->toBeNull();

    $block = Block::first();
    Storage::disk('public')->assertExists($block->data['path']);
});

it('rejects an image block without a file', function () {
    $this->postJson("/api/structuredContents/posts/{$this->post->id}/blocks", [
        'type' => 'image',
        'alt' => 'A photo',
    ])->assertStatus(422);
});

it('keeps the existing file when updating without a new one', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $this->post("/api/structuredContents/posts/{$this->post->id}/blocks", ['type' => 'image', 'file' => $file]);
    $block = Block::first();
    $originalPath = $block->data['path'];

    $response = $this->put("/api/structuredContents/blocks/{$block->id}", ['alt' => 'Updated alt']);

    $response->assertOk();
    $response->assertJsonPath('alt', 'Updated alt');
    expect($block->fresh()->data['path'])->toBe($originalPath);
    Storage::disk('public')->assertExists($originalPath);
});

it('replaces the file on update and deletes the old one', function () {
    $original = UploadedFile::fake()->image('original.jpg');
    $this->post("/api/structuredContents/posts/{$this->post->id}/blocks", ['type' => 'image', 'file' => $original]);
    $block = Block::first();
    $originalPath = $block->data['path'];

    $replacement = UploadedFile::fake()->image('replacement.jpg');
    $response = $this->put("/api/structuredContents/blocks/{$block->id}", ['file' => $replacement]);

    $response->assertOk();
    $newPath = $block->fresh()->data['path'];
    expect($newPath)->not->toBe($originalPath);
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing($originalPath);
});

it('deletes the underlying file when the block is destroyed', function () {
    $file = UploadedFile::fake()->image('photo.jpg');
    $this->post("/api/structuredContents/posts/{$this->post->id}/blocks", ['type' => 'image', 'file' => $file]);
    $block = Block::first();
    $path = $block->data['path'];

    $this->delete("/api/structuredContents/blocks/{$block->id}")->assertNoContent();

    Storage::disk('public')->assertMissing($path);
});
