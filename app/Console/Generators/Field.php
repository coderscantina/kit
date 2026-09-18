<?php

declare(strict_types=1);

namespace App\Console\Generators;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * One column of a generated feature, and every line the generators derive
 * from it. `make:feature --fields` parses them from the command line; every
 * later generator reads them back from the feature's create migration, so the
 * migration stays the one place a column is declared.
 */
final readonly class Field
{
    /** Columns every feature has and no generator treats as a field. */
    private const array RESERVED = ['id', 'version', 'created_at', 'updated_at'];

    public function __construct(
        public string $column,
        public FieldType $type,
        public bool $nullable = false,
    ) {}

    /**
     * `title:string body:text? published_at:datetime?`, separated by spaces or
     * commas. A trailing `?` makes the column nullable; a bare name is a string.
     *
     * @return list<self>
     */
    public static function parse(string $spec): array
    {
        $fields = [];

        foreach (preg_split('/[\s,]+/', trim($spec), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            [$column, $type] = array_pad(explode(':', $part, 2), 2, 'string');
            $nullable = str_ends_with($type, '?') || str_ends_with($column, '?');
            $column = Str::snake(rtrim($column, '?'));
            $type = rtrim($type, '?');

            if (preg_match('/^[a-z][a-z0-9_]*$/', $column) !== 1 || in_array($column, self::RESERVED, true)) {
                throw new InvalidArgumentException("'{$column}' is not a usable column name.");
            }

            $fieldType = FieldType::fromSpec($type) ?? throw new InvalidArgumentException(
                "Unknown field type '{$type}' for '{$column}'. Use one of: ".implode(', ', array_map(fn (FieldType $case): string => $case->spec(), FieldType::cases())).'.'
            );

            $fields[$column] = new self($column, $fieldType, $nullable);
        }

        if ($fields === []) {
            throw new InvalidArgumentException('--fields is empty.');
        }

        return array_values($fields);
    }

    /**
     * The fields of an existing feature, read from its create migration. A
     * feature without one (a hand-written model) falls back to `name`.
     *
     * @return list<self>
     */
    public static function fromMigration(string $table): array
    {
        $fields = [];

        foreach (self::migrationLines($table) as $line) {
            if (preg_match('/\$table->(\w+)\(\'(\w+)\'/', $line, $match) !== 1) {
                continue;
            }

            $type = FieldType::fromColumn($match[1]);

            if ($type === null || in_array($match[2], self::RESERVED, true)) {
                continue;
            }

            $fields[] = new self($match[2], $type, str_contains($line, '->nullable()'));
        }

        return $fields === [] ? [new self('name', FieldType::String)] : $fields;
    }

    /** Whether the feature's table carries the `version` column Versioned needs. */
    public static function isVersioned(string $table): bool
    {
        foreach (self::migrationLines($table) as $line) {
            if (str_contains($line, "('version')")) {
                return true;
            }
        }

        return false;
    }

    /**
     * The first field the tests can assert on by value: dates are skipped,
     * because what goes in is not byte for byte what comes back.
     *
     * @param  list<self>  $fields
     */
    public static function primary(array $fields): ?self
    {
        foreach ($fields as $field) {
            if (in_array($field->type, [FieldType::String, FieldType::Text], true)) {
                return $field;
            }
        }

        return null;
    }

    public function property(): string
    {
        return Str::camel($this->column);
    }

    public function label(): string
    {
        return Str::headline($this->column);
    }

    public function migration(): string
    {
        $line = "\$table->{$this->type->column()}('{$this->column}')";

        if ($this->nullable) {
            $line .= '->nullable()';
        } elseif ($this->type === FieldType::Boolean) {
            $line .= '->default(false)';
        }

        return $line.';';
    }

    public function cast(): ?string
    {
        return match ($this->type) {
            FieldType::Integer => 'integer',
            FieldType::Boolean => 'boolean',
            FieldType::Date => 'date',
            FieldType::DateTime => 'datetime',
            default => null,
        };
    }

    public function modelProperty(): string
    {
        $type = $this->type->isDate() ? 'Carbon' : $this->type->php();

        return '@property '.$type.($this->nullable ? '|null' : '')." \${$this->column}";
    }

    public function factory(): string
    {
        $value = match ($this->type) {
            FieldType::String => 'fake()->words(3, true)',
            FieldType::Text => 'fake()->paragraph()',
            FieldType::Integer => 'fake()->numberBetween(1, 1000)',
            FieldType::Boolean => 'fake()->boolean()',
            FieldType::Date => 'fake()->date()',
            FieldType::DateTime => 'fake()->dateTime()',
        };

        return "'{$this->column}' => {$value},";
    }

    public function dataParam(): string
    {
        return 'public '.($this->nullable ? '?' : '').$this->type->php()." \${$this->property()},";
    }

    /** The named argument that fills this field from `$variable` in `fromModel()`. */
    public function fromModel(string $variable): string
    {
        $value = "\${$variable}->{$this->column}";

        if ($this->type->isDate()) {
            $format = $this->type === FieldType::Date ? 'toDateString' : 'toIso8601String';
            $value .= ($this->nullable ? '?->' : '->')."{$format}()";
        }

        return "{$this->property()}: {$value},";
    }

    /**
     * The args property with its validation, as lines. Strings are bounded
     * with BoundedString, which unlike `max:` also refuses a blank string.
     *
     * @return list<string>
     */
    public function argsParam(): array
    {
        $max = $this->type === FieldType::Text ? 65535 : 255;

        $rule = match (true) {
            $this->type->isDate() => $this->nullable ? "#[Rule(['nullable', 'date'])]" : "#[Rule(['date'])]",
            $this->type === FieldType::String, $this->type === FieldType::Text => $this->nullable
                ? "#[Rule(['nullable', 'string', 'max:{$max}'])]"
                : "#[Rule(new BoundedString(1, {$max}))]",
            default => null,
        };

        return array_values(array_filter([$rule, $this->dataParam()]));
    }

    /** Whether argsParam() needs BoundedString imported. */
    public function usesBoundedString(): bool
    {
        return ! $this->nullable && in_array($this->type, [FieldType::String, FieldType::Text], true);
    }

    public function usesRule(): bool
    {
        return $this->type->isDate() || in_array($this->type, [FieldType::String, FieldType::Text], true);
    }

    /** The FormRequest rule list, keyed by the data property the client sends. */
    public function requestRule(): string
    {
        $max = $this->type === FieldType::Text ? 65535 : 255;
        $presence = $this->nullable ? "'nullable'" : "'required'";

        $rules = match ($this->type) {
            FieldType::String, FieldType::Text => $this->nullable ? "'nullable', 'string', 'max:{$max}'" : "new BoundedString(1, {$max})",
            FieldType::Integer => "{$presence}, 'integer'",
            FieldType::Boolean => "{$presence}, 'boolean'",
            FieldType::Date, FieldType::DateTime => "{$presence}, 'date'",
        };

        return "'{$this->property()}' => [{$rules}],";
    }

    /** The column write from a validated request, where a nullable key may be absent. */
    public function requestWrite(): string
    {
        return "'{$this->column}' => \$input['{$this->property()}']".($this->nullable ? ' ?? null' : '').',';
    }

    public function assignment(string $variable = 'args'): string
    {
        return "'{$this->column}' => \${$variable}->{$this->property()},";
    }

    /** A valid value as a PHP literal, for generated tests. */
    public function samplePhp(string $hint): string
    {
        return match ($this->type) {
            FieldType::String => "'{$hint}'",
            FieldType::Text => "'Some text.'",
            FieldType::Integer => '7',
            FieldType::Boolean => 'true',
            FieldType::Date => "'2026-01-15'",
            FieldType::DateTime => "'2026-01-15 10:00:00'",
        };
    }

    /** The same value as the data class would serialize it, as a TS literal. */
    public function sampleTs(string $hint): string
    {
        return match ($this->type) {
            FieldType::DateTime => "'2026-01-15T10:00:00+00:00'",
            default => $this->samplePhp($hint),
        };
    }

    /**
     * @param  list<string>  $lines
     */
    public static function indent(array $lines, int $spaces): string
    {
        $pad = str_repeat(' ', $spaces);

        return implode("\n", array_map(fn (string $line): string => $line === '' ? '' : $pad.$line, $lines));
    }

    /**
     * @return list<string>
     */
    private static function migrationLines(string $table): array
    {
        $files = glob(database_path("migrations/*_create_{$table}_table.php")) ?: [];

        if ($files === []) {
            return [];
        }

        return file($files[0], FILE_IGNORE_NEW_LINES) ?: [];
    }
}
