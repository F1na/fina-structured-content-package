<?php

namespace Fina\StructuredContent\Console\Commands;

use Fina\StructuredContent\BlockTypeRegistry;
use Illuminate\Console\Command;

/**
 * Lists every block type currently registered in the BlockTypeRegistry —
 * the package's own defaults (text/image/heading) plus whatever the
 * consuming app registered in its own provider. Useful to check, in a given
 * environment, which "type" keys are actually accepted by the block
 * endpoints without having to read every provider's boot() method.
 */
class ListBlockTypesCommand extends Command
{
    protected $signature = 'blocks:types';

    protected $description = 'List the block types registered in the BlockTypeRegistry';

    public function handle(BlockTypeRegistry $registry): int
    {
        $types = $registry->toArray();

        if (empty($types)) {
            $this->info('No block type registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Class'],
            collect($types)
                ->map(fn (string $class, string $key) => [$key, $class])
                ->values()
                ->all()
        );

        return self::SUCCESS;
    }
}
