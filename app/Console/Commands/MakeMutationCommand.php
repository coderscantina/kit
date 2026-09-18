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
 * Creates a transactional mutation, the args class it validates through, the
 * data class it returns, and a feature test asserting the commit, the single
 * invalidation batch and the 403 for a user without the ability. The args
 * and the write follow the columns in the feature's create migration.
 *
 * `--versioned` writes the other kind: a mutation on a row a form holds
 * open, locked against the version the form read, with its 409 test and an
 * edit dialog on useReactiveForm.
 */
class MakeMutationCommand extends Command
{
    use RendersFields;
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:mutation
        {name : The client-facing name, e.g. posts.create}
        {--versioned : Edit an existing row against the version the form read, with a 409 on conflict and an edit dialog}';

    protected $description = 'Create a reactive mutation, its args and data classes and its feature test';

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
        $versioned = (bool) $this->option('versioned');

        if ($versioned && ! $this->modelIsVersioned($names)) {
            $this->components->error(
                "{$feature} is not versioned. Give the model `use Versioned;` (Kit\\Reactive\\Concurrency\\Versioned) "
                ."and its create migration `\$table->unsignedInteger('version')->default(1);`, or scaffold it with `make:feature {$feature} --versioned`."
            );

            return self::FAILURE;
        }

        $replacements = [
            ...$names,
            ...$this->fieldReplacements($names, Field::fromMigration($names['table']), Field::isVersioned($names['table'])),
            'name' => $name,
            'class' => $class,
            'data' => "{$feature}Data",
            'args' => "{$class}Args",
            'namespace' => 'App\\Data',
            'extra_args' => $versioned ? "        public string \$id,\n        public int \$version,\n" : '',
        ];

        $this->writeStub($this->stub('feature.data'), app_path("Data/{$feature}Data.php"), $replacements, quiet: true);
        // One args class per mutation, not one per feature: two mutations on
        // the same model rarely take the same arguments.
        $this->writeStub($this->stub('args'), app_path("Data/{$class}Args.php"), [...$replacements, 'class' => "{$class}Args"]);
        $this->writeStub($this->stub($versioned ? 'mutation.versioned' : 'mutation'), app_path("Mutations/{$feature}/{$class}.php"), $replacements);
        $this->writeStub($this->stub($versioned ? 'mutation.versioned.test' : 'mutation.test'), base_path("tests/Feature/{$feature}/{$class}Test.php"), $replacements);

        if ($versioned) {
            $this->writeStub($this->stub('feature.dialog'), resource_path("js/components/{$names['page']}/{$class}Dialog.vue"), $replacements);

            foreach (['en', 'de'] as $locale) {
                $file = resource_path("js/i18n/{$locale}.json");
                $this->setJsonKey($file, "{$names['resource']}.edit", $locale === 'de' ? 'Bearbeiten' : "Edit {$feature}");
                $this->setJsonKey($file, "{$names['resource']}.conflict", $locale === 'de' ? 'Jemand anderes hat {field} gleichzeitig geändert.' : 'Someone else changed {field} while you were editing.');
                $this->setJsonKey($file, "{$names['resource']}.useTheirs", $locale === 'de' ? 'Deren Stand übernehmen' : 'Use theirs');
            }
        }

        // useReactiveMutation always translates mutations.<name>.error, so a
        // missing key would echo the key back at the user.
        foreach (['en' => 'Something went wrong.', 'de' => 'Etwas ist schiefgelaufen.'] as $locale => $message) {
            $this->setJsonKey(resource_path("js/i18n/{$locale}.json"), "mutations.{$name}.error", $message);
        }

        $this->formatWritten();

        // The client names the mutation off Kit.ReactiveMap, so it only
        // typechecks once the generated types know about it.
        $this->call('types:generate');

        $this->newLine();
        $this->components->info($versioned
            ? "Mutation {$name} created, with resources/js/components/{$names['page']}/{$class}Dialog.vue. Open the dialog from a row: <{$class}Dialog v-model:open=\"editing\" :row=\"row\" />."
            : "Mutation {$name} created and typed on the client.");

        return self::SUCCESS;
    }

    /**
     * @param  array{feature: string, table: string, resource: string, page: string, title: string}  $names
     */
    private function modelIsVersioned(array $names): bool
    {
        $model = (string) file_get_contents(app_path("Models/{$names['feature']}.php"));

        return str_contains($model, 'use Versioned;') && Field::isVersioned($names['table']);
    }
}
