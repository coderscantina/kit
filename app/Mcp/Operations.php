<?php

declare(strict_types=1);

namespace App\Mcp;

use BackedEnum;
use Kit\Reactive\Registry\Catalog;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * The reactive queries and mutations described for a model: each name, the
 * first paragraph of its class docblock, and its arguments read off the
 * args class constructor. Nothing is written by hand, so a generated query
 * is callable over MCP the moment it exists.
 */
final class Operations
{
    public function __construct(
        private readonly Catalog $catalog,
    ) {}

    /**
     * @return array{queries: array<int, array<string, mixed>>, mutations: array<int, array<string, mixed>>}
     */
    public function describe(): array
    {
        return [
            'queries' => $this->each($this->catalog->queries()),
            'mutations' => $this->each($this->catalog->mutations()),
        ];
    }

    /**
     * @param  array<string, class-string>  $classes
     * @return array<int, array<string, mixed>>
     */
    private function each(array $classes): array
    {
        ksort($classes);
        $out = [];

        foreach ($classes as $name => $class) {
            /** @var class-string $args */
            $args = $class::args();

            $out[] = [
                'name' => $name,
                'description' => $this->summary($class),
                'args' => $this->args($args),
            ];
        }

        return $out;
    }

    /**
     * @param  class-string  $class
     */
    private function summary(string $class): string
    {
        $doc = (string) (new ReflectionClass($class))->getDocComment();
        $lines = [];

        foreach (preg_split('/\R/', $doc) ?: [] as $line) {
            $line = trim(ltrim(trim($line), '/*'));

            if (str_starts_with($line, '@')) {
                break;
            }

            if ($line === '' && $lines !== []) {
                break;
            }

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode(' ', $lines);
    }

    /**
     * @param  class-string  $class
     * @return array<string, string>
     */
    private function args(string $class): array
    {
        $constructor = (new ReflectionClass($class))->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $out = [];

        foreach ($constructor->getParameters() as $parameter) {
            $out[$parameter->getName()] = $this->type($parameter);
        }

        return $out;
    }

    /** `string`, `int|null`, `"unseen"|"seen"|"archived"`, with `(optional)` when it has a default. */
    private function type(ReflectionParameter $parameter): string
    {
        $type = $parameter->getType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;

        if (is_subclass_of($name, BackedEnum::class)) {
            $name = implode('|', array_map(
                fn (BackedEnum $case): string => json_encode($case->value) ?: '',
                $name::cases(),
            ));
        }

        if ($type?->allowsNull() && ! str_contains($name, 'null')) {
            $name .= '|null';
        }

        return $parameter->isDefaultValueAvailable() ? "{$name} (optional)" : $name;
    }
}
