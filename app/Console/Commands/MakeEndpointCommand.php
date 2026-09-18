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
 * The REST path, generated: an invokable controller, its FormRequest, the
 * action that does the work, the route, a method on the feature's `api`
 * resource and a feature test. For what the browser posts to, uploads to
 * or is redirected back to; a live list or detail view is a reactive query.
 *
 * The request and the action follow the columns in the feature's create
 * migration, so the endpoint works on the first run: it creates a row.
 */
class MakeEndpointCommand extends Command
{
    use RendersFields;
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:endpoint {name : resource.verb, e.g. posts.import (POST /api/posts/import)}';

    protected $description = 'Create a REST endpoint: controller, request, action, route, api resource method and test';

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
        $domain = Str::pluralStudly($feature);
        $class = Str::studly($verb).$feature;
        $path = Str::kebab($verb);
        $fields = Field::fromMigration($names['table']);
        $versioned = Field::isVersioned($names['table']);
        $primary = Field::primary($fields);
        $strict = array_filter($fields, fn (Field $field): bool => ! $field->nullable) !== [];

        $replacements = [
            ...$names,
            ...$this->fieldReplacements($names, $fields, $versioned),
            'name' => $name,
            'class' => $class,
            'domain' => $domain,
            'path' => $path,
            'rules' => Field::indent(array_map(fn (Field $field): string => $field->requestRule(), $fields), 12),
            'request_imports' => array_filter($fields, fn (Field $field): bool => $field->usesBoundedString()) === [] ? '' : 'use App\\Rules\\BoundedString;',
            'request_writes' => Field::indent(array_map(fn (Field $field): string => $field->requestWrite(), $fields), 12),
            'assert_json' => $primary === null ? '' : "->assertJsonPath('{$primary->property()}', 'created')",
            'invalid_test' => $strict ? $this->invalidTest($names['page'], $path, $names['table']) : '',
        ];

        $this->writeStub($this->stub('endpoint.controller'), app_path("Http/Controllers/{$domain}/{$class}Controller.php"), $replacements);
        $this->writeStub($this->stub('endpoint.request'), app_path("Http/Requests/{$domain}/{$class}Request.php"), $replacements);
        $this->writeStub($this->stub('endpoint.action'), app_path("Actions/{$domain}/{$class}.php"), $replacements);
        $this->writeStub($this->stub('endpoint.test'), base_path("tests/Feature/{$feature}/{$class}EndpointTest.php"), $replacements);

        $this->addImport(base_path('routes/app.php'), "use App\\Http\\Controllers\\{$domain}\\{$class}Controller;");
        $this->insertAtMarker(base_path('routes/app.php'), 'api', [
            "Route::post('{$names['page']}/{$path}', {$class}Controller::class)->name('{$names['page']}.{$path}');",
        ]);

        $this->registerResource($names, $domain);
        $omit = $versioned ? "'id' | 'version'" : "'id'";
        $this->insertAtMarker(resource_path("js/api/resources/{$names['page']}.ts"), 'methods', [
            Str::camel($verb)."(payload: Omit<App.Data.{$feature}Data, {$omit}>): Promise<App.Data.{$feature}Data> {",
            "  return this.client.post(`\${this.basePath}/{$path}`, payload)",
            '}',
        ]);

        $this->formatWritten();

        $this->newLine();
        $this->components->info("POST /api/{$names['page']}/{$path} is routed. Call it with api.".Str::camel($names['page']).'.'.Str::camel($verb).'(payload).');

        return self::SUCCESS;
    }

    /**
     * The feature's `api` resource class and its entry in `~/api`, once.
     *
     * @param  array{feature: string, table: string, resource: string, page: string, title: string}  $names
     */
    private function registerResource(array $names, string $domain): void
    {
        $created = $this->writeStub($this->stub('endpoint.resource'), resource_path("js/api/resources/{$names['page']}.ts"), [...$names, 'domain' => $domain], quiet: true);

        if (! $created) {
            return;
        }

        $index = resource_path('js/api/index.ts');
        $this->addImport($index, "import { {$domain}Resource } from '~/api/resources/{$names['page']}'");
        $this->insertAtMarker($index, 'resources', [Str::camel($names['page']).": new {$domain}Resource(client),"]);
    }

    private function invalidTest(string $page, string $path, string $table): string
    {
        return <<<PHP

            #[Test]
            public function an_invalid_payload_is_a_422_and_writes_nothing(): void
            {
                \$this->createAndActAs(role: 'admin');

                \$this->postJson('/api/{$page}/{$path}', [])->assertUnprocessable();

                \$this->assertDatabaseCount('{$table}', 0);
            }
        PHP;
    }
}
