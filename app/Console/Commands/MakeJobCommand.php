<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\WritesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * A QueuedJob subclass. The base class snapshots the ambient container
 * bindings around execute() and calls handleFailure() once, after the last
 * attempt, so neither has to be written by hand.
 */
class MakeJobCommand extends Command
{
    use WritesStubs;

    protected $signature = 'make:job {name : Job class name, e.g. RebuildIndex}';

    protected $description = 'Create a QueuedJob subclass with execute() and handleFailure()';

    public function handle(): int
    {
        $class = Str::studly((string) $this->argument('name'));

        $this->writeStub($this->stub('job'), app_path("Jobs/{$class}.php"), ['class' => $class]);

        return self::SUCCESS;
    }
}
