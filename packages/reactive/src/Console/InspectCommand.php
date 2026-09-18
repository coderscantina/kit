<?php

declare(strict_types=1);

namespace Kit\Reactive\Console;

use Illuminate\Console\Command;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Dep;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Registry\Computation;

/**
 * Dumps the live computations from the registry, so "why did or didn't this
 * query push?" is one command instead of a log hunt. `--json` is the shape a
 * coding agent reads; the table is the same data for a human.
 *
 * @phpstan-type LastRecomputeShape array{at: string, query_ms: float, via: string, changed: bool}
 * @phpstan-type ComputationShape array{key: string, args: object, subscribers: int, created_at: string, last_mutation_id: int, result_hash: string, result_bytes: int, result_inline: bool, tables: array<int, string>, table_level: array<int, string>, reads: array<int, array{table: string, column: string|null, value: string|null}>, last_recompute: LastRecomputeShape|null}
 * @phpstan-type QueryShape array{name: string, class: string|null, tables: array<int, string>, computations: array<int, ComputationShape>}
 * @phpstan-type Document array{registry: string, current_mutation_id: int, inline_result_bytes: int, query: string|null, queries: array<int, QueryShape>}
 */
class InspectCommand extends Command
{
    protected $signature = 'reactive:inspect
        {query? : Only this query name, e.g. posts.list}
        {--json : Print one JSON document instead of tables}';

    protected $description = 'Show live reactive computations: subscribers, last recompute, result size and dependencies';

    public function handle(Registry $registry, Catalog $catalog): int
    {
        /** @var string|null $only */
        $only = $this->argument('query');
        $limit = (int) config('reactive.inline_result_bytes', 8192);

        $groups = [];
        foreach ($registry->computations() as $computation) {
            if ($only === null || $computation->query === $only) {
                $groups[$computation->query][] = $computation;
            }
        }

        if ($only !== null) {
            $groups[$only] ??= [];
        }

        ksort($groups);

        $queries = [];
        foreach ($groups as $name => $computations) {
            usort($computations, fn (Computation $a, Computation $b) => $a->key <=> $b->key);
            $queries[] = $this->describeQuery((string) $name, $computations, $registry, $catalog, $limit);
        }

        $known = $only === null || $queries[0]['class'] !== null || $queries[0]['computations'] !== [];

        $document = [
            'registry' => (string) config('reactive.registry'),
            'current_mutation_id' => $registry->currentMutationId(),
            'inline_result_bytes' => $limit,
            'query' => $only,
            'queries' => $queries,
        ];

        if ($this->option('json')) {
            $this->line(json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->render($document);
        }

        if (! $known) {
            if (! $this->option('json')) {
                $this->components->error("No query is registered as '{$only}'.");
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, Computation>  $computations
     * @return QueryShape
     */
    private function describeQuery(string $name, array $computations, Registry $registry, Catalog $catalog, int $limit): array
    {
        $tables = [];
        foreach ($computations as $computation) {
            array_push($tables, ...$computation->tables, ...array_map(fn (Dep $dep) => $dep->table, $computation->deps));
        }
        $tables = array_values(array_unique($tables));
        sort($tables);

        return [
            'name' => $name,
            'class' => $catalog->query($name),
            'tables' => $tables,
            'computations' => array_map(fn (Computation $computation) => $this->describeComputation($computation, $registry, $limit), $computations),
        ];
    }

    /**
     * @return ComputationShape
     */
    private function describeComputation(Computation $computation, Registry $registry, int $limit): array
    {
        $bytes = strlen($computation->result);
        $last = $computation->lastRecompute;

        return [
            'key' => $computation->key,
            'args' => (object) $computation->args,
            'subscribers' => count($registry->subscribersOf($computation->key)),
            'created_at' => date(DATE_ATOM, $computation->createdAt),
            'last_mutation_id' => $computation->lastMutationId,
            'result_hash' => $computation->resultHash,
            'result_bytes' => $bytes,
            'result_inline' => $bytes <= $limit,
            'tables' => $computation->tables,
            'table_level' => $computation->tableLevelTables(),
            'reads' => array_map(fn (Dep $dep) => $dep->toArray(), $computation->deps),
            'last_recompute' => $last === null ? null : [
                'at' => date(DATE_ATOM, $last->at),
                'query_ms' => $last->queryMs,
                'via' => $last->via,
                'changed' => $last->changed,
            ],
        ];
    }

    /**
     * @param  Document  $document
     */
    private function render(array $document): void
    {
        $this->components->twoColumnDetail('Registry', $document['registry']);
        $this->components->twoColumnDetail('Current mutation id', (string) $document['current_mutation_id']);

        if ($document['queries'] === []) {
            $this->components->info('No live computations.');

            return;
        }

        foreach ($document['queries'] as $query) {
            $this->newLine();
            $this->line("<options=bold>{$query['name']}</> ".($query['class'] ?? '<fg=red>not registered</>'));
            $this->line('  tables: '.($query['tables'] === [] ? '-' : implode(', ', $query['tables'])));

            if ($query['computations'] === []) {
                $this->line('  no live computations: nobody subscribes, so nothing recomputes or pushes');

                continue;
            }

            $rows = [];
            foreach ($query['computations'] as $c) {
                $last = $c['last_recompute'];
                $reads = $c['reads'];

                $rows[] = [
                    substr($c['key'], 0, 8),
                    json_encode($c['args'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    $c['subscribers'],
                    $c['last_mutation_id'],
                    $last === null ? 'never' : $last['at'],
                    $last === null ? '-' : $last['query_ms'].' ms',
                    $last === null ? '-' : $last['via'],
                    $last === null ? '-' : ($last['changed'] ? 'yes' : 'no'),
                    substr($c['result_hash'], 0, 8),
                    $c['result_bytes'].($c['result_inline'] ? '' : ' (hash only)'),
                    $reads === [] ? '-' : implode(', ', array_map(fn (array $dep) => $dep['column'] === null ? $dep['table'] : "{$dep['table']}.{$dep['column']}={$dep['value']}", $reads)),
                ];
            }

            $this->table(['Key', 'Args', 'Subs', 'Mutation', 'Recomputed', 'Query', 'Via', 'Changed', 'Hash', 'Bytes', 'reads()'], $rows);
        }
    }
}
