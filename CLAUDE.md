# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`fina/structured-content` (namespace `Fina\StructuredContent`) is a private, reusable Composer package that provides generic infrastructure for block-based structured content in Laravel applications — a simplified Strapi-like system. The package ships storage, CRUD, ordering (`up`/`down` endpoints that swap a block with its neighbor), and authorization; every consuming project registers its own block types and models without ever modifying this package.

The package is developed and tested standalone via Orchestra Testbench (no host Laravel app in this repo).

## Commands

```bash
composer install          # install dependencies
vendor/bin/pest           # run the full test suite
vendor/bin/pest tests/Feature/BlockControllerTest.php          # run a single test file
vendor/bin/pest --filter="creates a block via store"           # run a single test by name
composer validate          # validate composer.json/composer.lock
composer dump-autoload     # regenerate the PSR-4 autoloader after adding/moving classes
```

There is no build step (no compiled assets, no lint script configured).

## Architecture

### The generic/specific boundary is the whole point

`BlockController` never contains `instanceof` checks or `if ($type === '...')` conditionals on a concrete block type or blockable model. Everything domain-specific is resolved through two runtime registries, both empty by default and populated only by the consuming application (typically in an app-level `BlocksServiceProvider`, declared **after** the package's own provider in `bootstrap/providers.php` — Composer auto-discovery loads the package provider first, so the app's Gate override and registry registrations must run afterward):

- **`BlockTypeRegistry`** — maps a `type` string key (e.g. `text`, `callout`) to the `BlockType` implementation class. The package pre-registers five defaults: `text` (`TextBlockType`), `image` (`ImageBlockType`), `heading` (`HeadingBlockType`), `video` (`VideoBlockType`), `quote` (`QuoteBlockType`).
- **`BlocksRegistrar`** — whitelists which Eloquent models may act as a "blockable" and under which route key (e.g. `articles` → `Article::class`). Ships empty. `BlockController::resolveBlockable()` goes only through this registrar; an unwhitelisted route segment is a 404, never arbitrary class instantiation.

Any new capability that needs per-type behavior should be added as a method on the `BlockType` contract (with a sensible no-op/pass-through default in `AbstractBlockType`) and invoked generically from `BlockController` — not as a conditional in the controller.

### `BlockType` contract lifecycle hooks

`Fina\StructuredContent\Contracts\BlockType` (implemented via `AbstractBlockType`, which supplies defaults) defines the full lifecycle a block type can hook into:

- `key(): string` — the `type` value stored on the block.
- `rules(): array` — validation on create.
- `rulesForUpdate(): array` — validation on update; defaults to `rules()`. Override when a field required on create should be optional on update (see `ImageBlockType`, where a re-sent file isn't mandatory).
- `prepareForStorage(array $validated, Request $request, array $previousData): array` — transforms the validated payload into what's actually persisted in `data`, called after validation and before saving. This is where a block type that owns an external resource (a file) moves it to storage and swaps it for metadata, since something like an `UploadedFile` can't be JSON-encoded into the `data` column. `$previousData` is empty on create, the block's current `data` on update — used to decide whether to keep/replace/delete a file.
- `afterDelete(array $data): void` — cleanup hook called right before a block is deleted.
- `toArray(array $data): array` — shapes persisted data into the API response.
- `authorizeSave(?Authenticatable $user, $blockable): bool` — per-type authorization independent of the general Gate; defaults to `true`.

There is deliberately **no shared media library**: each block that needs a file (currently only `ImageBlockType`) uploads and owns it directly via these hooks, in the same request that creates/updates the block. See `ImageBlockType` for the reference implementation (stores to `config('blocks.media_disk')`, deletes the old file on replace/delete).

### Two-layer authorization

1. **`Gate::define('manageBlocks', ...)`** — defined fail-closed (`return false`) in the package's own `BlocksServiceProvider::boot()`. This is deliberate: the package can't presume anything about the host app's roles, so every route 403s until the consuming app overrides the Gate. **The closure's `$user` parameter must be typed `?Authenticatable`** — Laravel silently skips a Gate closure for guest (unauthenticated) requests if its first parameter doesn't accept `null`, falling back to an implicit denial without ever running your logic. This is easy to get wrong and looks like the override "isn't working."
2. **`BlockType::authorizeSave()`** — checked in addition to the Gate on `store`/`update` only, for restricting a specific block type (e.g. admin-only) independently of the general blockable-level permission.

`BlockController` checks the Gate before resolving the block type or running validation, so an unauthorized request never pays for that extra work.

### `UnknownBlockTypeException` renders as 422, not 404

`BlockTypeRegistry::resolve()` throws this when a `type` key isn't registered. It implements `render()` directly (self-rendering exception, supported since Laravel 5.5) to return 422: the `type` comes from the client's request payload on store/update, making it a semantically invalid input, not a missing route resource. 404 is reserved for the *blockable* whitelist check in `BlockController::resolveBlockable()`, which is a distinct failure mode.

### Database naming conventions (specific to this project, not Laravel defaults)

- Table names: singular, camelCase (`block`, not `blocks`).
- Column names: camelCase (`blockableId`, `blockableType`, `createdAt`, `updatedAt`) — this requires overriding Eloquent's `CREATED_AT`/`UPDATED_AT` constants and passing explicit column names to `morphTo()` on every model (see `Block` model).
- `_` is reserved for association/pivot tables. This package has none — the polymorphic relation lives on two columns of the `block` table itself.
- These conventions apply to the schema only. HTTP routes (`/{blockableType}/{blockableId}/blocks`) and config keys (`route_prefix`) keep normal REST/English conventions and are unrelated to DB naming.

### Routes and SubstituteBindings

Routes are loaded via `loadRoutesFrom(routes/blocks.php)` directly in the package's `ServiceProvider::boot()`, not through the host app's `routes/api.php`, but the actual route definitions live in one place: `StructuredContent::routes()` (`src/StructuredContent.php`). `routes/blocks.php` just wraps that call in the default `config('blocks.route_prefix')` group with no auth middleware — single source of truth either way.

Because these routes aren't loaded through the host's `routes/api.php`, they don't automatically get the host's `web`/`api` middleware group — `StructuredContent::routes()` applies `SubstituteBindings::class` itself, since it's required for the implicit `Block $block` route-model binding used by `update()`/`destroy()`. Forgetting this means `{block}` stays a raw string and Laravel injects an empty `Block` instance instead of resolving it from the database.

For an API segmented by guard (e.g. `/admin`, `/healthFacility`, each with its own guard in `config/auth.php`), the default single unguarded route group doesn't fit. Set `config('blocks.register_routes')` to `false` to stop the package auto-registering it, then call `StructuredContent::routes()` once per segment, inside the host's own `Route::prefix(...)->middleware('auth:<guard>')->group(...)`. The `manageBlocks` Gate needs no guard-awareness of its own: Laravel's `Authenticate` middleware calls `Auth::shouldUse($guard)` on success, so `Gate::denies()`/`$request->user()` already resolve against whichever guard authenticated the request — the Gate closure only needs to branch on the resolved `$user`'s class.

Migrations are publish-only (`vendor:publish --tag=blocks-migrations`), not auto-loaded by the provider — the consuming app runs `php artisan migrate` itself after publishing.

### Testing setup

Tests run against Orchestra Testbench, not a host Laravel app. `tests/TestCase.php` registers `BlocksServiceProvider` as the only package provider and loads both the package's own migrations (`database/migrations`) and a test fixture migration (`tests/Fixtures/migrations`) for the `post` table backing `tests/Fixtures/Models/Post.php` — a stand-in blockable used across the test suite in place of a real project's `Article`/`Page` model.

## Non-negotiable constraints when modifying this package

- No business logic (specific roles, concrete block types belonging to one project) inside the package — only the generic infrastructure plus the five example-grade default block types (`text`, `image`, `heading`, `video`, `quote`).
- `BlockController` must never contain an `if ($type === '...')` or an `instanceof` check on an application-specific model.
- The `manageBlocks` Gate default must stay fail-closed.
