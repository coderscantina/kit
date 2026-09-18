<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\RendersFields;
use App\Console\Commands\Concerns\ResolvesFeatureNames;
use App\Console\Commands\Concerns\WritesStubs;
use App\Console\Generators\Field;
use App\Console\Generators\FieldType;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

/**
 * Scaffolds a feature across the layers it touches and wires it into
 * everything that has to know about it: the ability registry, the router, the
 * access map, the sidebar and both message files. The model, the policy and
 * the factory find each other by name, so there is no registration to keep in
 * sync.
 *
 * It finishes by running make:query for `<resource>.list` and types:generate,
 * so what you get is a page that renders real rows, not a placeholder.
 *
 * `--fields` names the columns once and every file follows: migration, model
 * fillable and casts, factory, data class, page columns and labels. Later
 * generators read them back from the migration. `--versioned` adds the
 * `version` column and the Versioned trait a form-edited row needs.
 *
 * Nothing is overwritten. Run it twice and the second run reports what it
 * left alone, so it is safe to re-run after adding a marker back.
 */
class MakeFeatureCommand extends Command
{
    use RendersFields;
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:feature
        {name : Singular feature name, e.g. Post}
        {--fields= : Columns as name:type, a trailing ? for nullable, e.g. "title:string body:text? published_at:datetime?". Types: string, text, integer, boolean, date, datetime. Default: name:string}
        {--versioned : Add the version column and the Versioned trait, for rows a form edits (make:mutation --versioned)}';

    protected $description = 'Create a feature folder with its model, policy, provider, list query, page, tests and registry entries';

    public function handle(Filesystem $files): int
    {
        $names = $this->featureNames((string) $this->argument('name'));
        ['feature' => $feature, 'table' => $table, 'resource' => $resource, 'page' => $page, 'title' => $title] = $names;

        if ($files->exists(app_path("Models/{$feature}.php"))) {
            $this->components->warn("Feature {$feature} already exists; only missing files are created.");
        }

        try {
            $fields = $this->option('fields') === null
                ? [new Field('name', FieldType::String)]
                : Field::parse((string) $this->option('fields'));
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $replacements = [
            ...$names,
            ...$this->fieldReplacements($names, $fields, (bool) $this->option('versioned')),
        ];

        $this->writeStub($this->stub('feature.model'), app_path("Models/{$feature}.php"), $replacements);
        $this->writeStub($this->stub('feature.factory'), database_path("factories/{$feature}Factory.php"), $replacements);
        // Keyed on the table, not the timestamp: a second run must not add a
        // second migration that creates a table the first one already has.
        if (glob(database_path("migrations/*_create_{$table}_table.php")) === []) {
            $this->writeStub($this->stub('feature.migration'), database_path('migrations/'.date('Y_m_d_His')."_create_{$table}_table.php"), $replacements);
        }
        $this->writeStub($this->stub('feature.data'), app_path("Data/{$feature}Data.php"), $replacements);
        $this->writeStub($this->stub('feature.policy'), app_path("Policies/{$feature}Policy.php"), $replacements);
        $this->writeStub($this->stub('feature.test'), base_path("tests/Feature/{$feature}/{$feature}PolicyTest.php"), $replacements);
        $this->writeStub($this->stub('feature.page'), resource_path("js/pages/{$page}/Index.vue"), $replacements);
        $this->writeStub($this->stub('feature.vitest'), base_path("tests/js/pages/{$page}/Index.test.ts"), $replacements);

        $this->register($names);
        $this->registerFieldLabels($resource, $fields);
        $this->formatWritten();

        // The list query the page renders, so a fresh feature is a working
        // page rather than a placeholder. make:query is still usable on its
        // own; a second run reports the files it left alone. It also runs
        // types:generate, which the page needs to typecheck.
        $this->call('make:query', ['name' => "{$resource}.list"]);

        $this->newLine();
        $next = $this->option('versioned')
            ? "php artisan make:mutation {$resource}.create, then make:mutation {$resource}.update --versioned"
            : "php artisan make:mutation {$resource}.create";
        $this->components->info("Feature {$feature} is wired up and {$resource}.list renders on /{$page}. Next: {$next}");

        return self::SUCCESS;
    }

    /**
     * @param  array{feature: string, table: string, resource: string, page: string, title: string}  $names
     */
    private function register(array $names): void
    {
        ['resource' => $resource, 'page' => $page, 'title' => $title] = $names;

        $abilities = ["'{$resource}.view',", "'{$resource}.manage',"];

        $this->insertAtMarker(config_path('abilities.php'), 'abilities', $abilities);
        // Owner and admin get the new resource; member is deliberately left
        // alone, because "everyone can see it" is a decision, not a default.
        $this->insertAtMarker(config_path('abilities.php'), 'role:owner', $abilities);
        $this->insertAtMarker(config_path('abilities.php'), 'role:admin', $abilities);

        $this->insertAtMarker(resource_path('js/router/index.ts'), 'routes', [
            '{',
            "  path: '/{$page}',",
            "  name: '{$page}',",
            "  component: () => import('~/pages/{$page}/Index.vue'),",
            "  meta: { layout: 'app' },",
            '},',
        ]);

        $this->insertAtMarker(resource_path('js/lib/access-control.ts'), 'nav', [
            "{ labelKey: 'nav.{$page}', icon: 'box', routeName: '{$page}' },",
        ]);

        // oxfmt drops the quotes on a key that is already a valid identifier,
        // so quote only the kebab-cased ones or format:check fails on the
        // line the generator just wrote.
        $key = preg_match('/^[A-Za-z_$][A-Za-z0-9_$]*$/', $page) === 1 ? $page : "'{$page}'";

        $this->insertAtMarker(resource_path('js/lib/access-control.ts'), 'access', [
            "{$key}: { abilities: '{$resource}.view' },",
        ]);

        foreach (['en', 'de'] as $locale) {
            $file = resource_path("js/i18n/{$locale}.json");

            $this->setJsonKey($file, "nav.{$page}", $title);
            $this->setJsonKey($file, "{$resource}.title", $title);
            $this->setJsonKey($file, "{$resource}.empty", $locale === 'de' ? 'Noch keine Einträge.' : 'Nothing here yet.');
        }
    }
}
