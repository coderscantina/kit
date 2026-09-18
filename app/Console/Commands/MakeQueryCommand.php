<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RendersFields;
use App\Console\Commands\Concerns\ResolvesFeatureNames;
use App\Console\Commands\Concerns\WritesStubs;
use App\Console\Generators\Field;
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
    use RendersFields;
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

        $replacements = [
            ...$names,
            ...$this->fieldReplacements($names, Field::fromMigration($names['table']), Field::isVersioned($names['table'])),
            'name' => $name,
            'class' => $class,
            'data' => "{$feature}Data",
            'args' => "{$class}Args",
            'namespace' => 'App\\Data',
        ];

        // The feature's data class is shared by every query and mutation on
        // it; make:feature already wrote one, so this only fills a gap.
        $this->writeStub($this->stub('feature.data'), app_path("Data/{$feature}Data.php"), $replacements, quiet: true);
        $this->writeStub($this->stub('query'), app_path("Queries/{$feature}/{$class}.php"), $replacements);
        $this->writeStub($this->stub('query.test'), base_path("tests/Feature/{$feature}/{$class}Test.php"), $replacements);

        $this->formatWritten();

        // The page names the query off Kit.ReactiveMap, so it only
        // typechecks once the generated types know about it.
        $this->call('types:generate');

        $this->newLine();
        $this->components->info("Query {$name} created and typed on the client.");

        return self::SUCCESS;
    }
}
