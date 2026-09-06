<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesFeatureNames;
use App\Console\Commands\Concerns\WritesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates a transactional mutation, the args class it validates through, the
 * data class it returns, and a feature test asserting the commit, the single
 * invalidation batch and the 403 for a user without the ability.
 */
class MakeMutationCommand extends Command
{
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:mutation {name : The client-facing name, e.g. posts.create}';

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

        $replacements = [
            ...$names,
            'name' => $name,
            'class' => $class,
            'data' => "{$feature}Data",
            'args' => "{$class}Args",
            'namespace' => 'App\\Data',
        ];

        $this->writeStub($this->stub('data'), app_path("Data/{$feature}Data.php"), [...$replacements, 'class' => "{$feature}Data"]);
        // One args class per mutation, not one per feature: two mutations on
        // the same model rarely take the same arguments.
        $this->writeStub($this->stub('args'), app_path("Data/{$class}Args.php"), [...$replacements, 'class' => "{$class}Args"]);
        $this->writeStub($this->stub('mutation'), app_path("Mutations/{$feature}/{$class}.php"), $replacements);
        $this->writeStub($this->stub('mutation.test'), base_path("tests/Feature/{$feature}/{$class}Test.php"), $replacements);

        // useReactiveMutation always translates mutations.<name>.error, so a
        // missing key would echo the key back at the user.
        foreach (['en' => 'Something went wrong.', 'de' => 'Etwas ist schiefgelaufen.'] as $locale => $message) {
            $this->setJsonKey(resource_path("js/i18n/{$locale}.json"), "mutations.{$name}.error", $message);
        }

        $this->newLine();
        $this->components->info("Mutation {$name} created. Run `php artisan types:generate` to type it on the client.");

        return self::SUCCESS;
    }
}
