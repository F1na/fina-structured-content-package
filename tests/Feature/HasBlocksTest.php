<?php

use Fina\StructuredContent\Models\Block;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\Fixtures\Models\Post;

beforeEach(function () {
    $this->post = Post::create(['title' => 'Hello world']);
});

function makeBlock(Post $post, string $content, int $position): Block
{
    return Block::create([
        'blockableId' => $post->id,
        'blockableType' => $post->getMorphClass(),
        'type' => 'text',
        'data' => ['content' => $content],
        'position' => $position,
    ]);
}

it('exposes blocks as a relation ordered by position', function () {
    makeBlock($this->post, 'second', 1);
    makeBlock($this->post, 'first', 0);

    expect($this->post->blocks->pluck('data.content')->all())->toBe(['first', 'second']);
});

it('only returns the blocks of its own blockable', function () {
    $other = Post::create(['title' => 'Other']);
    makeBlock($other, 'foreign', 0);
    makeBlock($this->post, 'mine', 0);

    expect($this->post->blocks)->toHaveCount(1);
});

it('can be eager loaded', function () {
    makeBlock($this->post, 'a', 0);

    $post = Post::with('blocks')->find($this->post->id);

    expect($post->relationLoaded('blocks'))->toBeTrue()
        ->and($post->blocks)->toHaveCount(1);
});

it('serializes blocks like the API', function () {
    makeBlock($this->post, 'Hello', 0);

    $array = $this->post->blocks->toArray();

    expect($array)->toHaveCount(1)
        ->and($array[0])->toMatchArray(['type' => 'text', 'position' => 0, 'content' => 'Hello']);
});

it('serializes blocks like the API when the blockable is serialized', function () {
    makeBlock($this->post, 'Hello', 0);

    $json = $this->post->load('blocks')->toArray();

    expect($json['blocks'][0])->toMatchArray(['type' => 'text', 'content' => 'Hello']);
});

it('deletes its blocks when deleted', function () {
    makeBlock($this->post, 'a', 0);
    makeBlock($this->post, 'b', 1);

    $this->post->delete();

    expect(Block::count())->toBe(0);
});

it('works with an enforced morph map', function () {
    Relation::morphMap(['post' => Post::class]);

    try {
        makeBlock($this->post, 'mapped', 0);

        expect($this->post->fresh()->blocks)->toHaveCount(1)
            ->and(Block::first()->blockableType)->toBe('post')
            ->and(Block::first()->blockable->is($this->post))->toBeTrue();
    } finally {
        Relation::morphMap([], false);
    }
});
