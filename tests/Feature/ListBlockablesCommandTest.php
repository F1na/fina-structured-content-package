<?php

use Fina\StructuredContent\BlocksRegistrar;
use Tests\Fixtures\Models\Post;

it('reports no blockable registered by default', function () {
    $this->artisan('blocks:blockables')
        ->assertSuccessful()
        ->expectsOutputToContain('No blockable registered.');
});

it('lists a registered blockable', function () {
    app(BlocksRegistrar::class)->registerBlockable('posts', Post::class);

    $this->artisan('blocks:blockables')
        ->assertSuccessful()
        ->expectsOutputToContain('posts');
});
