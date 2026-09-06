<?php

declare(strict_types=1);

namespace Kit\Reactive\Invalidation;

use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Kit\Reactive\Contracts\Registry;

/**
 * Collects row changes for the current transaction and hands them to the
 * Invalidator as one batch when the outermost transaction commits. A
 * rollback drops them. Outside a transaction (a plain save in a controller
 * or job) the change is handed over immediately.
 *
 * Listens to the connection's transaction events rather than requiring the
 * caller to register afterCommit hooks, so the invariant holds for every
 * write path, not just mutations. Octane flushes this instance per request.
 */
final class ChangeBuffer
{
    /** @var array<int, Change> */
    private array $changes = [];

    private ?int $committedMutationId = null;

    /**
     * The transaction level that counts as "outside a transaction". Zero in
     * production; tests that wrap themselves in a transaction
     * (RefreshDatabase) raise it so commits of their savepoints flush.
     */
    private int $baseLevel = 0;

    public function __construct(
        private readonly Registry $registry,
    ) {}

    public function record(Change $change): void
    {
        if (DB::connection()->transactionLevel() <= $this->baseLevel) {
            $this->dispatch([$change]);

            return;
        }

        $this->changes[] = $change;
    }

    public function onCommitted(TransactionCommitted $event): void
    {
        if ($event->connection->transactionLevel() > $this->baseLevel) {
            return;
        }

        $this->flush();
    }

    public function onRolledBack(TransactionRolledBack $event): void
    {
        if ($event->connection->transactionLevel() <= $this->baseLevel) {
            $this->changes = [];
        }
    }

    /**
     * The id the last flush took, handed to the mutation runner once.
     */
    public function takeCommittedMutationId(): ?int
    {
        $id = $this->committedMutationId;
        $this->committedMutationId = null;

        return $id;
    }

    public function setBaseTransactionLevel(int $level): void
    {
        $this->baseLevel = $level;
        $this->changes = [];
    }

    public function pending(): int
    {
        return count($this->changes);
    }

    private function flush(): void
    {
        if ($this->changes === []) {
            return;
        }

        $changes = $this->changes;
        $this->changes = [];

        $this->committedMutationId = $this->dispatch($changes);
    }

    /**
     * @param  array<int, Change>  $changes
     */
    private function dispatch(array $changes): int
    {
        // Taken after commit and before any recompute, so a recompute this
        // batch triggers reads a counter that already covers the commit.
        $mutationId = $this->registry->nextMutationId();

        // Resolved here rather than held: `Reactive::fake()` swaps bindings
        // after this singleton may already exist.
        app(Invalidator::class)->invalidate($changes, $mutationId);

        return $mutationId;
    }
}
