<?php

declare(strict_types=1);

namespace Kit\Reactive\TypeScript;

use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Registry\Catalog;
use ReflectionClass;
use ReflectionNamedType;
use Spatie\LaravelData\Data;

/**
 * Emits the `Kit.ReactiveMap` declaration: for every registered query and
 * mutation, an `args` type from rules() and a `result` type from the
 * attribute or the return type of handle().
 */
final class ReactiveMapGenerator
{
    public function __construct(
        private readonly Catalog $catalog,
    ) {}

    public function generate(): string
    {
        $entries = [];

        foreach ($this->catalog->queries() as $name => $class) {
            $entries[$name] = $this->entry($class, ReactiveQuery::class);
        }

        foreach ($this->catalog->mutations() as $name => $class) {
            $entries[$name] = $this->entry($class, ReactiveMutation::class);
        }

        ksort($entries);

        $lines = [];
        foreach ($entries as $name => [$args, $result]) {
            $lines[] = "    '{$name}': {\n      args: {$args}\n      result: {$result}\n    }";
        }

        $body = $lines === [] ? '' : "\n".implode("\n", $lines)."\n  ";

        return "declare namespace Kit {\n  export type ReactiveMap = {{$body}}\n}\n";
    }

    /**
     * @param  class-string  $class
     * @param  class-string<ReactiveQuery|ReactiveMutation>  $attribute
     * @return array{string, string}
     */
    private function entry(string $class, string $attribute): array
    {
        $reflection = new ReflectionClass($class);
        $instance = app($class);

        /** @var array<string, array<int, mixed>|string> $rules */
        $rules = $instance->rules();
        // The object type is rendered at depth 1; the map nests it three levels deep.
        $args = str_replace("\n", "\n      ", RulesToTypeScript::objectType($rules));

        $meta = $reflection->getAttributes($attribute)[0]->newInstance();
        $result = 'unknown';

        if ($meta->result !== null) {
            $result = $this->typeName($meta->result);
            if ($meta->list) {
                $result = "Array<{$result}>";
            }
        } else {
            $type = $reflection->getMethod('handle')->getReturnType();
            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), Data::class)) {
                $result = $this->typeName($type->getName());
                if ($type->allowsNull()) {
                    $result .= ' | null';
                }
            }
        }

        return [$args, $result];
    }

    /**
     * `App\Features\Chat\Data\MessageData` → `App.Features.Chat.Data.MessageData`,
     * the name the GlobalNamespaceWriter gives it.
     */
    private function typeName(string $class): string
    {
        return str_replace('\\', '.', ltrim($class, '\\'));
    }
}
