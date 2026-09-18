<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesFeatureNames;
use App\Console\Commands\Concerns\WritesStubs;
use App\Console\Generators\Field;
use App\Console\Generators\FieldType;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Writes the list contract's filter class for a feature: one method per
 * column, `q` over the text columns, a sort allow list and the whitelist
 * that keeps the inherited `limit`/`offset` helpers out of the client's
 * reach. Columns come from the feature's create migration.
 */
class MakeFilterCommand extends Command
{
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:filter {feature : The feature the list shows, e.g. Post}';

    protected $description = 'Create a list filter with a sort allow list and a whitelist, and its test';

    public function handle(): int
    {
        try {
            $names = $this->existingFeature((string) $this->argument('feature'));
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $fields = Field::fromMigration($names['table']);
        $searchable = array_values(array_filter($fields, fn (Field $field): bool => in_array($field->type, [FieldType::String, FieldType::Text], true)));
        $columns = [...array_map(fn (Field $field): string => $field->column, $fields), 'created_at'];
        $quoted = fn (array $list): string => implode(', ', array_map(fn (string $item): string => "'{$item}'", $list));

        $replacements = [
            ...$names,
            'sortable' => $quoted($columns),
            'whitelist' => $quoted([...($searchable === [] ? [] : ['q']), ...$columns, 'sort']),
            'methods' => implode("\n", [
                ...($searchable === [] ? [] : [$this->search($searchable)]),
                ...array_map($this->method(...), $fields),
                $this->method(new Field('created_at', FieldType::DateTime)),
            ]),
            'search_test' => $this->searchTest($names['feature'], Field::primary($fields)),
        ];

        $this->writeStub($this->stub('filter'), app_path("Http/Filters/{$names['feature']}Filter.php"), $replacements);
        $this->writeStub($this->stub('filter.test'), base_path("tests/Feature/{$names['feature']}/{$names['feature']}FilterTest.php"), $replacements);
        $this->formatWritten();

        $this->newLine();
        $this->components->info("Apply it with {$names['feature']}::query()->filter(new {$names['feature']}Filter(\$params)). docs/lists.md has the client half.");

        return self::SUCCESS;
    }

    /**
     * @param  list<Field>  $fields
     */
    private function search(array $fields): string
    {
        $where = implode("\n", array_map(
            fn (Field $field, int $index): string => '                ->'.($index === 0 ? 'where' : 'orWhere')."('{$field->column}', 'like', \$like)",
            $fields,
            array_keys($fields),
        ));

        return <<<PHP

            /** Free text over the text columns; ANDed with every other filter. */
            public function q(mixed \$value): void
            {
                \$term = trim((string) \$value);

                if (\$term === '') {
                    return;
                }

                \$like = '%'.addcslashes(\$term, '%_\\\\').'%';

                \$this->builder->where(fn (\$query) => \$query
        {$where});
            }
        PHP;
    }

    private function method(Field $field): string
    {
        $body = match ($field->type) {
            FieldType::Date, FieldType::DateTime => "\$this->applyAdvancedDateFilter('{$field->column}', \$value);",
            FieldType::Boolean => "\$this->builder->where('{$field->column}', filter_var(\$value, FILTER_VALIDATE_BOOLEAN));",
            default => "\$this->applyDynamicFilter('{$field->column}', \$value);",
        };

        return <<<PHP

            public function {$field->column}(mixed \$value): void
            {
                {$body}
            }
        PHP;
    }

    private function searchTest(string $feature, ?Field $primary): string
    {
        if ($primary === null) {
            return '';
        }

        return <<<PHP

            #[Test]
            public function q_searches_the_text_columns(): void
            {
                {$feature}::factory()->create(['{$primary->column}' => 'Zyxwv one']);
                {$feature}::factory()->create(['{$primary->column}' => 'Other two']);

                \$found = {$feature}::query()->filter(new {$feature}Filter(['q' => 'zyxwv']))->pluck('{$primary->column}')->all();

                \$this->assertSame(['Zyxwv one'], \$found);
            }
        PHP;
    }
}
