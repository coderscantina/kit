<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use App\Console\Generators\Field;
use App\Console\Generators\FieldType;
use Illuminate\Support\Str;

/**
 * Turns a feature's fields into the stub replacements every generator
 * shares, so the model, the migration, the data class, the args, the page
 * and the tests all say the same thing about a column.
 */
trait RendersFields
{
    /**
     * @param  array{feature: string, table: string, resource: string, page: string, title: string}  $names
     * @param  list<Field>  $fields
     * @return array<string, string>
     */
    protected function fieldReplacements(array $names, array $fields, bool $versioned): array
    {
        $feature = $names['feature'];
        $variable = Str::camel($feature);
        $hint = "First {$feature}";
        $primary = Field::primary($fields);
        $casts = array_filter(array_map(fn (Field $field): ?string => ($cast = $field->cast()) === null ? null : "'{$field->column}' => '{$cast}',", $fields));
        $dates = array_values(array_unique(array_filter(array_map(
            fn (Field $field): ?string => match ($field->type) {
                FieldType::Date => 'date',
                FieldType::DateTime => 'dateTime',
                default => null,
            },
            $fields,
        ))));
        $booleans = array_filter($fields, fn (Field $field): bool => $field->type === FieldType::Boolean);

        return [
            'data' => "{$feature}Data",
            'variable' => $variable,

            'model_imports' => $versioned ? 'use Kit\\Reactive\\Concurrency\\Versioned;' : '',
            'properties' => Field::indent(array_map(
                fn (string $line): string => "* {$line}",
                [...array_map(fn (Field $field): string => $field->modelProperty(), $fields), ...($versioned ? ['@property int $version'] : [])],
            ), 1),
            'fillable' => implode(', ', array_map(fn (Field $field): string => "'{$field->column}'", $fields)),
            'traits' => $versioned ? '    use Versioned;' : '',
            'casts' => $casts === [] ? '' : implode("\n", [
                '',
                '    /**',
                '     * @return array<string, string>',
                '     */',
                '    protected function casts(): array',
                '    {',
                '        return [',
                Field::indent(array_values($casts), 12),
                '        ];',
                '    }',
            ]),

            'columns' => Field::indent([
                ...array_map(fn (Field $field): string => $field->migration(), $fields),
                ...($versioned ? ["\$table->unsignedInteger('version')->default(1);"] : []),
            ], 12),
            'definitions' => Field::indent(array_map(fn (Field $field): string => $field->factory(), $fields), 12),

            'params' => Field::indent([
                ...array_map(fn (Field $field): string => $field->dataParam(), $fields),
                ...($versioned ? ['public int $version,'] : []),
            ], 8),
            'assignments' => Field::indent([
                ...array_map(fn (Field $field): string => $field->fromModel($variable), $fields),
                ...($versioned ? ["version: \${$variable}->currentVersion(),"] : []),
            ], 12),

            'args_imports' => implode("\n", array_filter([
                array_filter($fields, fn (Field $field): bool => $field->usesBoundedString()) === [] ? null : 'use App\\Rules\\BoundedString;',
                array_filter($fields, fn (Field $field): bool => $field->usesRule()) === [] ? null : 'use Spatie\\LaravelData\\Attributes\\Validation\\Rule;',
            ])),
            'args_params' => Field::indent(array_merge(...array_map(fn (Field $field): array => $field->argsParam(), $fields)), 8),
            'writes' => Field::indent(array_map(fn (Field $field): string => $field->assignment(), $fields), 12),
            'payload' => implode(', ', array_map(fn (Field $field): string => "'{$field->property()}' => {$field->samplePhp('created')}", $fields)),
            'assert_result' => $primary === null ? '' : "->assertJsonPath('result.{$primary->property()}', 'created')",
            'factory_state' => $primary === null ? '' : "['{$primary->column}' => 'pushed']",
            'pushed' => $primary === null
                ? 'count($result) === 1'
                : "\$result[0]['{$primary->property()}'] === 'pushed'",

            'page_imports' => implode("\n", array_filter([
                $booleans === [] ? null : "import Icon from '~/components/Icon.vue'",
                $dates === [] ? null : "import { useFormat } from '~/composables/useFormat'",
            ])),
            'format' => $dates === [] ? '' : 'const { '.implode(', ', $dates).' } = useFormat()',
            'headers' => Field::indent(array_map(
                fn (Field $field): string => "<TableHead>{{ t('{$names['resource']}.fields.{$field->property()}') }}</TableHead>",
                $fields,
            ), 10),
            'cells' => Field::indent(array_merge(...array_map(
                fn (Field $field, int $index): array => $this->tableCell($names['resource'], $field, $index === 0),
                $fields,
                array_keys($fields),
            )), 10),
            'colspan' => (string) count($fields),
            'row' => '{ '.implode(', ', [
                "id: '1'",
                ...array_map(fn (Field $field): string => "{$field->property()}: {$field->sampleTs($hint)}", $fields),
                ...($versioned ? ['version: 1'] : []),
            ]).' }',
            'expected' => $primary === null ? "'{$names['title']}'" : $primary->sampleTs($hint),

            'dialog_imports' => implode("\n", array_filter([
                $this->hasType($fields, FieldType::String, FieldType::Integer, FieldType::Date, FieldType::DateTime) ? "import { Input } from '~/components/ui/input'" : null,
                $this->hasType($fields, FieldType::Boolean) ? "import { Switch } from '~/components/ui/switch'" : null,
                $this->hasType($fields, FieldType::Text) ? "import { Textarea } from '~/components/ui/textarea'" : null,
            ])),
            'field_list' => implode(', ', array_map(fn (Field $field): string => "'{$field->property()}'", $fields)),
            'inputs' => Field::indent(array_merge(...array_map(
                fn (Field $field): array => $this->dialogInput($names['page'], $names['resource'], $field),
                $fields,
            )), 8),
        ];
    }

    /**
     * @param  list<Field>  $fields
     */
    private function hasType(array $fields, FieldType ...$types): bool
    {
        return array_filter($fields, fn (Field $field): bool => in_array($field->type, $types, true)) !== [];
    }

    /**
     * One labelled input for the edit dialog. Bound through model-value and
     * an explicit conversion rather than v-model: Input and Textarea hand
     * back `string | number`, and the form field is typed off the row.
     *
     * @return list<string>
     */
    private function dialogInput(string $page, string $resource, Field $field): array
    {
        $id = "{$page}-".Str::kebab($field->property());
        $value = "form.fields.{$field->property()}";
        $label = "<Label for=\"{$id}\">{{ t('{$resource}.fields.{$field->property()}') }}</Label>";

        if ($field->type === FieldType::Boolean) {
            return [
                '<div class="flex items-center gap-2">',
                '  <Switch',
                "    id=\"{$id}\"",
                '    :model-value="'.$value.($field->nullable ? ' ?? false' : '').'"',
                "    @update:model-value=\"{$value} = \$event\"",
                '  />',
                "  {$label}",
                '</div>',
            ];
        }

        $cast = $field->type === FieldType::Integer ? 'Number($event)' : 'String($event)';
        $convert = $field->nullable ? "\$event === '' ? null : {$cast}" : $cast;
        $type = match ($field->type) {
            FieldType::Integer => 'number',
            FieldType::Date => 'date',
            default => 'text',
        };

        return [
            '<div class="grid gap-1.5">',
            "  {$label}",
            '  <'.($field->type === FieldType::Text ? 'Textarea' : 'Input'),
            "    id=\"{$id}\"",
            ...($field->type === FieldType::Text ? [] : ["    type=\"{$type}\""]),
            "    :model-value=\"{$value}\"",
            "    @update:model-value=\"{$value} = {$convert}\"",
            '  />',
            '</div>',
        ];
    }

    /**
     * The `<resource>.fields.<property>` labels the page and the edit dialog
     * use, in both message files.
     *
     * @param  list<Field>  $fields
     */
    protected function registerFieldLabels(string $resource, array $fields): void
    {
        foreach (['en', 'de'] as $locale) {
            foreach ($fields as $field) {
                $this->setJsonKey(resource_path("js/i18n/{$locale}.json"), "{$resource}.fields.{$field->property()}", $field->label());
            }
        }
    }

    /**
     * A table cell for the list page. The first column carries no label: on
     * a phone the stacked row leads with it.
     *
     * @return list<string>
     */
    private function tableCell(string $resource, Field $field, bool $first): array
    {
        $property = "item.{$field->property()}";
        $label = $first ? '' : " :label=\"t('{$resource}.fields.{$field->property()}')\"";
        $class = match ($field->type) {
            FieldType::Text => ' class="max-w-md truncate"',
            FieldType::Integer, FieldType::Date, FieldType::DateTime => ' class="whitespace-nowrap"',
            default => '',
        };

        $content = match ($field->type) {
            FieldType::Boolean => "<Icon v-if=\"{$property}\" name=\"lucide:check\" />",
            FieldType::Date => $field->nullable ? "{{ {$property} ? date({$property}) : '–' }}" : "{{ date({$property}) }}",
            FieldType::DateTime => $field->nullable ? "{{ {$property} ? dateTime({$property}) : '–' }}" : "{{ dateTime({$property}) }}",
            default => $field->nullable ? "{{ {$property} ?? '–' }}" : "{{ {$property} }}",
        };

        return ["<TableCell{$label}{$class}>", "  {$content}", '</TableCell>'];
    }
}
