<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

/**
 * Pulls table names out of executed SQL: every identifier after FROM, JOIN,
 * UPDATE, INTO or DELETE FROM, with quoting, schema qualifiers, aliases and
 * the connection's table prefix stripped. Subqueries are skipped at the
 * FROM keyword and picked up at their own FROM.
 */
final class SqlTables
{
    private const string PATTERN = '/\b(?:from|join|update|into)\s+(?!\(|select\b)([`"]?)([a-zA-Z0-9_]+)\1(?:\s*\.\s*([`"]?)([a-zA-Z0-9_]+)\3)?/i';

    /**
     * @return array<int, string>
     */
    public static function extract(string $sql, string $prefix = ''): array
    {
        preg_match_all(self::PATTERN, $sql, $matches, PREG_SET_ORDER);

        $tables = [];

        foreach ($matches as $match) {
            $table = $match[4] ?? '';
            $table = $table !== '' ? $table : $match[2];

            if ($prefix !== '' && str_starts_with($table, $prefix)) {
                $table = substr($table, strlen($prefix));
            }

            if ($table === '' || strtolower($table) === 'dual') {
                continue;
            }

            $tables[$table] = true;
        }

        return array_keys($tables);
    }
}
