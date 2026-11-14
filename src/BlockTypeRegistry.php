<?php

namespace Fina\StructuredContent;

use Fina\StructuredContent\Contracts\BlockType;
use Illuminate\Support\Facades\Log;
use Fina\StructuredContent\Exceptions\UnknownBlockTypeException;

/**
 * Maps block type keys (e.g. "text", "callout") to the BlockType class that
 * implements them. Registered as an application singleton so both the
 * package's own default types and the consuming app's custom types accumulate
 * in the same registry over the course of bootstrapping.
 */
class BlockTypeRegistry
{
    /**
     * @var array<string, class-string<BlockType>>
     */
    protected array $types = [];

    public function register(string $blockTypeClass): void
    {
        $this->types[$blockTypeClass::key()] = $blockTypeClass;
    }

    public function resolve(string $key): BlockType
    {
        Log::info("Resolving block type for key: {$key}");
        Log::info("Available block types: " . implode(', ', array_keys($this->types)));
        if (! isset($this->types[$key])) {
            throw new UnknownBlockTypeException($key);
        }

        return app($this->types[$key]);
    }

    /**
     * @return array<int, string>
     */
    public function all(): array
    {
        return array_keys($this->types);
    }

    /**
     * @return array<string, class-string<BlockType>>
     */
    public function toArray(): array
    {
        return $this->types;
    }
}
