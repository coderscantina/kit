<?php

declare(strict_types=1);

namespace App\Console\Generators;

/**
 * The column types `make:feature --fields` understands. Each one decides the
 * migration column, the model cast, the data and args property, the factory
 * value and how the page renders it.
 *
 * A pure enum on purpose: types:generate turns every backed enum under app/
 * into a client type, and this one is generator tooling.
 */
enum FieldType
{
    case String;
    case Text;
    case Integer;
    case Boolean;
    case Date;
    case DateTime;

    /** The name `--fields` spells it with, e.g. `datetime`. */
    public function spec(): string
    {
        return strtolower($this->name);
    }

    public static function fromSpec(string $spec): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->spec() === $spec) {
                return $case;
            }
        }

        return null;
    }

    /** The Blueprint method that creates the column. */
    public function column(): string
    {
        return match ($this) {
            self::String => 'string',
            self::Text => 'text',
            self::Integer => 'integer',
            self::Boolean => 'boolean',
            self::Date => 'date',
            self::DateTime => 'dateTime',
        };
    }

    public static function fromColumn(string $method): ?self
    {
        return match ($method) {
            'string' => self::String,
            'text', 'mediumText', 'longText' => self::Text,
            'integer', 'unsignedInteger', 'bigInteger', 'unsignedBigInteger', 'smallInteger', 'tinyInteger' => self::Integer,
            'boolean' => self::Boolean,
            'date' => self::Date,
            'dateTime', 'dateTimeTz', 'timestamp', 'timestampTz' => self::DateTime,
            default => null,
        };
    }

    /** The PHP type on the model and on the args class. Dates travel as strings. */
    public function php(): string
    {
        return match ($this) {
            self::String, self::Text, self::Date, self::DateTime => 'string',
            self::Integer => 'int',
            self::Boolean => 'bool',
        };
    }

    public function isDate(): bool
    {
        return $this === self::Date || $this === self::DateTime;
    }
}
