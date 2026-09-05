<?php

declare(strict_types=1);

namespace Kit\Reactive\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Removes the cached name maps, so the next boot rediscovers them. Wired
 * into `optimize:clear` through the service provider.
 */
class ClearCommand extends Command
{
    protected $signature = 'reactive:clear';

    protected $description = 'Remove the cached reactive name maps';

    public function handle(Filesystem $files): int
    {
        $files->delete((string) config('reactive.cache_path'));
        $this->components->info('Reactive name maps cleared.');

        return self::SUCCESS;
    }
}
