<?php

use Fina\StructuredContent\BlocksRegistrar;
use Tests\Fixtures\Models\Post;

it('has no blockables registered by default', function () {
    $registrar = app(BlocksRegistrar::class);

    expect($registrar->resolveBlockableClass('posts'))->toBeNull();
});

it('resolves a blockable class once registered', function () {
    $registrar = app(BlocksRegistrar::class);
    $registrar->registerBlockable('posts', Post::class);

    expect($registrar->resolveBlockableClass('posts'))->toBe(Post::class);
});

it('exposes the full key => class map via toArray', function () {
    $registrar = app(BlocksRegistrar::class);

    expect($registrar->toArray())->toBe([]);

    $registrar->registerBlockable('posts', Post::class);

    expect($registrar->toArray())->toBe(['posts' => Post::class]);
});
