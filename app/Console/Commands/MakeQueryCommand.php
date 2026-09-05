<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesFeatureNames;
use App\Console\Commands\Concerns\WritesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates a subscribable query, the data class it serializes through, and a
 * feature test that asserts both halves of the contract: a row change pushes,
 * and a user without the ability gets a 403.
 */
class MakeQueryCommand extends Command
{
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:query {name : The client-facing name, e.g. posts.list}';

    protected $description = 'Create a reactive query, its data class and its feature test';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        try {
            [$segment, $verb] = $this->splitReactiveName($name);
            $names = $this->existingFeature($segment);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $feature = $names['feature'];
        $class = Str::studly($verb).$feature;
        $base = app_path("Features/{$feature}");

        $replacements = [
            ...$names,
            'name' => $name,
            'class' => $class,
            'data' => "{$feature}Data",
            'namespace' => "App\\Features\\{$feature}\\Data",
        ];

        // The feature's data class is shared by every query and mutation on
        // it; make:feature already wrote one, so this only fills a gap.
        $this->writeStub($this->stub('data'), "{$base}/Data/{$feature}Data.php", [...$replacements, 'class' => "{$feature}Data"]);
        $this->writeStub($this->stub('query'), "{$base}/Queries/{$class}.php", $replacements);
        $this->writeStub($this->stub('query.test'), "{$base}/Tests/{$class}Test.php", $replacements);

        $this->newLine();
        $this->components->info("Query {$name} created. Run `php artisan types:generate` to type it on the client.");

        return self::SUCCESS;
    }
}
