<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\WritesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * A laravel-data class carrying the #[TypeScript] attribute, so it lands in
 * generated.d.ts. Every data class in the app lives in App\Data.
 */
class MakeDataCommand extends Command
{
    use WritesStubs;

    protected $signature = 'make:data {name : Class name, e.g. Post or ListPostArgs}';

    protected $description = 'Create a laravel-data class with the TypeScript transformer attribute';

    public function handle(): int
    {
        $input = trim((string) $this->argument('name'));

        if ($input === '') {
            $this->components->error('A class name is required.');

            return self::FAILURE;
        }

        // `Post` becomes PostData; an args class keeps the name it was given,
        // because CreatePostArgsData reads like a typo.
        $class = Str::studly($input);
        $class = Str::endsWith($class, ['Data', 'Args']) ? $class : "{$class}Data";

        $this->writeStub($this->stub('data'), app_path("Data/{$class}.php"), [
            'class' => $class,
            'namespace' => 'App\\Data',
        ]);

        $this->newLine();
        $this->components->info("App\\Data\\{$class} created. Run `php artisan types:generate` to publish the type.");

        return self::SUCCESS;
    }
}
