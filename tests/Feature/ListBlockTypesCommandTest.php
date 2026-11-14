<?php

use Fina\StructuredContent\BlockTypeRegistry;

it('lists the package default block types', function () {
    $this->artisan('blocks:types')
        ->assertSuccessful()
        ->expectsOutputToContain('text')
        ->expectsOutputToContain('image')
        ->expectsOutputToContain('heading');
});

it('lists a custom block type registered by the consuming app', function () {
    app(BlockTypeRegistry::class)->register(\Tests\Fixtures\BlockTypes\FakeCustomBlockType::class);

    $this->artisan('blocks:types')
        ->assertSuccessful()
        ->expectsOutputToContain('custom');
});
