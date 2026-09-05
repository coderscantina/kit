<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesFeatureNames;
use App\Console\Commands\Concerns\WritesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * A laravel-data class carrying the #[TypeScript] attribute, so it lands in
 * generated.d.ts. Pass `Feature/Name` to put it inside a feature folder.
 */
class MakeDataCommand extends Command
{
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:data {name : Class name, optionally Feature/Name to place it in a feature}';

    protected $description = 'Create a laravel-data class with the TypeScript transformer attribute';

    public function handle(): int
    {
        $input = str_replace('\\', '/', (string) $this->argument('name'));
        $parts = array_values(array_filter(explode('/', $input)));

        if ($parts === []) {
            $this->components->error('A class name is required.');

            return self::FAILURE;
        }

        $class = Str::studly((string) array_pop($parts));
        $class = str_ends_with($class, 'Data') ? $class : "{$class}Data";

        if ($parts === []) {
            $namespace = 'App\\Data';
            $target = app_path("Data/{$class}.php");
        } else {
            try {
                $feature = $this->existingFeature((string) $parts[0])['feature'];
            } catch (RuntimeException $e) {
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            $namespace = "App\\Features\\{$feature}\\Data";
            $target = app_path("Features/{$feature}/Data/{$class}.php");
        }

        $this->writeStub($this->stub('data'), $target, ['class' => $class, 'namespace' => $namespace]);

        $this->newLine();
        $this->components->info("{$namespace}\\{$class} created. Run `php artisan types:generate` to publish the type.");

        return self::SUCCESS;
    }
}
