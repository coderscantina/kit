<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesFeatureNames;
use App\Console\Commands\Concerns\WritesStubs;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Scaffolds a feature folder and wires it into everything that has to know
 * about it: the ability registry, the router, the access map, the sidebar
 * and both message files. The service provider is discovered from the folder
 * by AppServiceProvider, so there is no list to keep in sync.
 *
 * Nothing is overwritten. Run it twice and the second run reports what it
 * left alone, so it is safe to re-run after adding a marker back.
 */
class MakeFeatureCommand extends Command
{
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:feature {name : Singular feature name, e.g. Post}';

    protected $description = 'Create a feature folder with its model, policy, provider, page, tests and registry entries';

    public function handle(Filesystem $files): int
    {
        $names = $this->featureNames((string) $this->argument('name'));
        ['feature' => $feature, 'table' => $table, 'resource' => $resource, 'page' => $page, 'title' => $title] = $names;

        if ($files->isDirectory(app_path("Features/{$feature}"))) {
            $this->components->warn("Feature {$feature} already exists; only missing files are created.");
        }

        $replacements = [
            'feature' => $feature,
            'table' => $table,
            'resource' => $resource,
            'page' => $page,
            'title' => $title,
            'class' => "{$feature}Data",
            'namespace' => "App\\Features\\{$feature}\\Data",
            'data' => "{$feature}Data",
        ];

        $base = app_path("Features/{$feature}");

        // Queries/ and Mutations/ stay empty until make:query and make:mutation
        // run; the directories exist so the shape of a feature is visible.
        foreach (['Queries', 'Mutations'] as $directory) {
            $files->ensureDirectoryExists("{$base}/{$directory}");
        }

        $this->writeStub($this->stub('feature.model'), "{$base}/Models/{$feature}.php", $replacements);
        $this->writeStub($this->stub('feature.factory'), "{$base}/Database/Factories/{$feature}Factory.php", $replacements);
        // Keyed on the table, not the timestamp: a second run must not add a
        // second migration that creates a table the first one already has.
        if (glob("{$base}/Database/Migrations/*_create_{$table}_table.php") === []) {
            $this->writeStub($this->stub('feature.migration'), "{$base}/Database/Migrations/".date('Y_m_d_His')."_create_{$table}_table.php", $replacements);
        }
        $this->writeStub($this->stub('data'), "{$base}/Data/{$feature}Data.php", $replacements);
        $this->writeStub($this->stub('feature.policy'), "{$base}/Policies/{$feature}Policy.php", $replacements);
        $this->writeStub($this->stub('feature.provider'), "{$base}/{$feature}ServiceProvider.php", $replacements);
        $this->writeStub($this->stub('feature.test'), "{$base}/Tests/{$feature}PolicyTest.php", $replacements);
        $this->writeStub($this->stub('feature.page'), resource_path("js/pages/{$page}/Index.vue"), $replacements);
        $this->writeStub($this->stub('feature.vitest'), base_path("tests/js/pages/{$page}/Index.test.ts"), $replacements);

        $this->register($names);

        $this->newLine();
        $this->components->info("Feature {$feature} is wired up. Next: php artisan make:query {$resource}.list");

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

        $this->insertAtMarker(resource_path('js/lib/access-control.ts'), 'access', [
            "'{$page}': { abilities: '{$resource}.view' },",
        ]);

        foreach (['en', 'de'] as $locale) {
            $file = resource_path("js/i18n/{$locale}.json");

            $this->setJsonKey($file, "nav.{$page}", $title);
            $this->setJsonKey($file, "{$resource}.title", $title);
            $this->setJsonKey($file, "{$resource}.empty", $locale === 'de' ? 'Noch keine Einträge.' : 'Nothing here yet.');
        }
    }
}
