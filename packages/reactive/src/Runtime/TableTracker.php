<?php

declare(strict_types=1);

namespace Kit\Reactive\Runtime;

use Illuminate\Database\Events\QueryExecuted;

/**
 * Collects the tables touched while a query's handle() runs. One
 * QueryExecuted listener is registered at boot and records into whichever
 * collector is active; collectors nest so a query that runs another query
 * still attributes reads correctly. Octane flushes this instance per request.
 */
final class TableTracker
{
    /** @var array<int, array<string, true>> */
    private array $stack = [];

    public function start(): void
    {
        $this->stack[] = [];
    }

    /**
     * @return array<int, string>
     */
    public function stop(): array
    {
        $tables = array_pop($this->stack) ?? [];

        // Reads inside a nested run also belong to the outer one.
        if ($this->stack !== []) {
            $outer = array_key_last($this->stack);
            $this->stack[$outer] = [...$this->stack[$outer], ...$tables];
        }

        return array_keys($tables);
    }

    public function record(QueryExecuted $event): void
    {
        if ($this->stack === []) {
            return;
        }

        $index = array_key_last($this->stack);

        foreach (SqlTables::extract($event->sql, $event->connection->getTablePrefix()) as $table) {
            $this->stack[$index][$table] = true;
        }
    }

    public function active(): bool
    {
        return $this->stack !== [];
    }
}
