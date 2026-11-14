<?php

namespace Fina\StructuredContent\Console\Commands;

use Fina\StructuredContent\BlocksRegistrar;
use Illuminate\Console\Command;

/**
 * Lists every blockable currently registered in the BlocksRegistrar — the
 * route keys (e.g. "articles") the consuming app whitelisted, and the
 * Eloquent class each one resolves to. Useful to check which
 * {blockableType} segments are actually reachable without reading through
 * every provider's boot() method.
 */
class ListBlockablesCommand extends Command
{
    protected $signature = 'blocks:blockables';

    protected $description = 'List the blockables registered in the BlocksRegistrar';

    public function handle(BlocksRegistrar $registrar): int
    {
        $blockables = $registrar->toArray();

        if (empty($blockables)) {
            $this->info('No blockable registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Class'],
            collect($blockables)
                ->map(fn (string $class, string $key) => [$key, $class])
                ->values()
                ->all()
        );

        return self::SUCCESS;
    }
}
