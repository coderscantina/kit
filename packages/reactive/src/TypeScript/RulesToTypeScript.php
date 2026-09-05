<?php

declare(strict_types=1);

namespace Kit\Reactive\TypeScript;

use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\In;

/**
 * Derives a TypeScript object type from a Laravel `rules()` array. Only the
 * shape matters to the client: which keys exist, whether they are optional
 * or nullable, and a coarse scalar type. Anything richer stays `unknown`.
 */
final class RulesToTypeScript
{
    /**
     * @param  array<string, array<int, mixed>|string>  $rules
     */
    public static function objectType(array $rules): string
    {
        if ($rules === []) {
            return 'Record<string, never>';
        }

        return self::render(self::tree($rules), 1);
    }

    /**
     * @param  array<string, array<int, mixed>|string>  $rules
     * @return array<string, mixed>
     */
    private static function tree(array $rules): array
    {
        /** @var array<string, mixed> $tree */
        $tree = [];

        foreach ($rules as $field => $set) {
            $parts = explode('.', $field);
            /** @var array<string, mixed> $node */
            $node = &$tree;

            foreach ($parts as $index => $part) {
                $last = $index === count($parts) - 1;

                if ($part === '*') {
                    if (! isset($node['__items'])) {
                        $node['__items'] = [];
                    }
                    $node = &$node['__items'];
                } else {
                    if (! isset($node['__props'][$part])) {
                        $node['__props'][$part] = [];
                    }
                    $node = &$node['__props'][$part];
                }

                if ($last) {
                    $node['__rules'] = self::normalize($set);
                }
            }

            unset($node);
        }

        return $tree;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function render(array $node, int $depth): string
    {
        $rules = $node['__rules'] ?? [];
        $nullable = in_array('nullable', $rules, true) ? ' | null' : '';

        if (isset($node['__props'])) {
            $pad = str_repeat('  ', $depth);
            $lines = [];

            foreach ($node['__props'] as $name => $child) {
                $childRules = $child['__rules'] ?? [];
                $optional = self::isOptional($childRules) ? '?' : '';
                $lines[] = "{$pad}{$name}{$optional}: ".self::render($child, $depth + 1);
            }

            return "{\n".implode("\n", $lines)."\n".str_repeat('  ', $depth - 1).'}'.$nullable;
        }

        if (isset($node['__items'])) {
            return 'Array<'.self::render($node['__items'], $depth).'>'.$nullable;
        }

        return self::scalar($rules).$nullable;
    }

    /**
     * @param  array<int, string>  $rules
     */
    private static function isOptional(array $rules): bool
    {
        if (in_array('required', $rules, true)) {
            return false;
        }

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'required_')) {
                return true;
            }
        }

        // An implicit rule object (BoundedString) or `present` makes the key
        // mandatory; anything else may be omitted.
        return ! in_array('present', $rules, true) && ! in_array('__implicit', $rules, true);
    }

    /**
     * @param  array<int, string>  $rules
     */
    private static function scalar(array $rules): string
    {
        foreach ($rules as $rule) {
            if (str_starts_with($rule, '__in:')) {
                return substr($rule, 5);
            }
        }

        foreach ($rules as $rule) {
            $name = strtok($rule, ':') ?: $rule;

            switch ($name) {
                case 'boolean':
                case 'accepted':
                case 'declined':
                    return 'boolean';
                case 'integer':
                case 'numeric':
                case 'decimal':
                    return 'number';
                case 'string':
                case 'email':
                case 'ulid':
                case 'uuid':
                case 'url':
                case 'date':
                case 'date_format':
                case 'alpha':
                case 'alpha_num':
                case 'alpha_dash':
                case 'ip':
                case 'json':
                case '__string':
                    return 'string';
                case 'array':
                case 'list':
                    return 'unknown[]';
            }
        }

        return 'unknown';
    }

    /**
     * Rule objects become markers the scalar mapper understands.
     *
     * @param  array<int, mixed>|string  $set
     * @return array<int, string>
     */
    private static function normalize(array|string $set): array
    {
        $rules = is_string($set) ? explode('|', $set) : $set;
        $normalized = [];

        foreach ($rules as $rule) {
            if (is_string($rule)) {
                if (str_starts_with($rule, 'in:')) {
                    $values = array_map(fn (string $v) => "'".str_replace("'", "\\'", $v)."'", explode(',', substr($rule, 3)));
                    $normalized[] = '__in:'.implode(' | ', $values);
                } else {
                    $normalized[] = $rule;
                }

                continue;
            }

            if ($rule instanceof In) {
                $normalized[] = '__in:'.self::inValues((string) $rule);

                continue;
            }

            if ($rule instanceof Enum) {
                $normalized[] = '__string';

                continue;
            }

            if (is_object($rule)) {
                if (property_exists($rule, 'implicit') && $rule->implicit === true) {
                    $normalized[] = '__implicit';
                }

                if (str_contains(strtolower($rule::class), 'string') || str_contains(strtolower($rule::class), 'password')) {
                    $normalized[] = '__string';
                }
            }
        }

        return $normalized;
    }

    private static function inValues(string $rule): string
    {
        $values = explode(',', substr($rule, 3));

        return implode(' | ', array_map(fn (string $v) => "'".str_replace(['"', "'"], '', $v)."'", $values));
    }
}
