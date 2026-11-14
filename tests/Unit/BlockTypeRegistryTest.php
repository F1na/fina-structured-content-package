<?php

use Fina\StructuredContent\BlockTypeRegistry;
use Fina\StructuredContent\BlockTypes\TextBlockType;
use Fina\StructuredContent\Exceptions\UnknownBlockTypeException;

it('resolves a registered block type to its instance', function () {
    $registry = app(BlockTypeRegistry::class);

    expect($registry->resolve('text'))->toBeInstanceOf(TextBlockType::class);
});

it('lists the package default block types', function () {
    $registry = app(BlockTypeRegistry::class);

    expect($registry->all())->toContain('text', 'image', 'heading');
});

it('throws UnknownBlockTypeException when resolving an unregistered key', function () {
    $registry = app(BlockTypeRegistry::class);

    expect(fn () => $registry->resolve('does-not-exist'))
        ->toThrow(UnknownBlockTypeException::class);
});

it('exposes the full key => class map via toArray', function () {
    $registry = app(BlockTypeRegistry::class);

    expect($registry->toArray())->toBe([
        'text' => TextBlockType::class,
        'image' => \Fina\StructuredContent\BlockTypes\ImageBlockType::class,
        'heading' => \Fina\StructuredContent\BlockTypes\HeadingBlockType::class,
        'video' => \Fina\StructuredContent\BlockTypes\VideoBlockType::class,
        'quote' => \Fina\StructuredContent\BlockTypes\QuoteBlockType::class,
    ]);
});
