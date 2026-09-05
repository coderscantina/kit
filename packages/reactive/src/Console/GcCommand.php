<?php

declare(strict_types=1);

namespace Kit\Reactive\Console;

use Illuminate\Console\Command;
use Kit\Reactive\Contracts\Registry;

class GcCommand extends Command
{
    protected $signature = 'reactive:gc {--flush : Drop every subscription and counter (development only)}';

    protected $description = 'Sweep dependency sets for subscriptions whose hash has expired';

    public function handle(Registry $registry): int
    {
        if ($this->option('flush')) {
            $registry->flush();
            $this->components->info('Registry flushed.');

            return self::SUCCESS;
        }

        $removed = $registry->gc();
        $this->components->info("Removed {$removed} orphaned dependency entries.");

        return self::SUCCESS;
    }
}
