<?php

namespace Fina\StructuredContent;

use Illuminate\Support\Facades\Log;

/**
 * Whitelists which Eloquent models may act as a "blockable" and under which
 * route key they are reachable (e.g. "articles" => Article::class).
 *
 * Deliberately ships empty: the package has no opinion on the host
 * application's domain models. The controller uses this registrar instead of
 * resolving models by class name directly, both to avoid arbitrary class
 * instantiation from a route segment and to keep the controller free of any
 * `instanceof`/model-specific logic.
 *
 * Pattern mirrors Laravel Horizon's HorizonServiceProvider-style registrars.
 */
class BlocksRegistrar
{
    /**
     * @var array<string, class-string>
     */
    protected array $blockables = [];

    public function registerBlockable(string $key, string $modelClass): static
    {
        $this->blockables[$key] = $modelClass;

        return $this;
    }

    public function resolveBlockableClass(string $key): ?string
    {
        return $this->blockables[$key] ?? null;
    }

    /**
     * @return array<string, class-string>
     */
    public function toArray(): array
    {
        return $this->blockables;
    }
}
