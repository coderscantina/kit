<?php

declare(strict_types=1);

namespace Tests\Feature\Reactive;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Pusher;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Invalidation\ChangeBuffer;
use Kit\Reactive\Invalidation\HasReactiveInvalidation;
use Kit\Reactive\Invalidation\Invalidate;
use Kit\Reactive\Jobs\RecomputeComputation;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Runtime\QueryRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Reactive\Note;
use Tests\Fixtures\Reactive\ReactiveFixtures;
use Tests\TestCase;

#[CoversClass(ChangeBuffer::class)]
#[CoversClass(HasReactiveInvalidation::class)]
#[CoversClass(Invalidate::class)]
#[CoversClass(RecomputeComputation::class)]
final class InvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        ReactiveFixtures::install();
        Reactive::fake();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function changes_inside_a_transaction_flush_as_one_batch_on_commit(): void
    {
        $base = app(Registry::class)->currentMutationId();

        $batches = Reactive::capturingInvalidations(function (): void {
            DB::transaction(function (): void {
                Note::query()->create(['owner_id' => $this->user->id, 'title' => 'a']);
                Note::query()->create(['owner_id' => $this->user->id, 'title' => 'b']);
                $this->assertSame(2, app(ChangeBuffer::class)->pending());
            });
        });

        $this->assertCount(1, $batches);
        $this->assertCount(2, $batches[0]->changeObjects());
        $this->assertSame($base + 1, $batches[0]->mutationId);
        $this->assertSame('notes', $batches[0]->changeObjects()[0]->table);
        $this->assertSame($this->user->id, $batches[0]->changeObjects()[0]->after['owner_id'] ?? null);
    }

    #[Test]
    public function a_rolled_back_transaction_dispatches_nothing(): void
    {
        $base = app(Registry::class)->currentMutationId();

        $batches = Reactive::capturingInvalidations(function (): void {
            try {
                DB::transaction(function (): void {
                    Note::query()->create(['owner_id' => $this->user->id, 'title' => 'a']);
                    throw new \RuntimeException('abort');
                });
            } catch (\RuntimeException) {
            }
        });

        $this->assertSame([], $batches);
        $this->assertSame($base, app(Registry::class)->currentMutationId());
    }

    #[Test]
    public function a_save_outside_a_transaction_dispatches_immediately(): void
    {
        $batches = Reactive::capturingInvalidations(fn () => Note::query()->create(['owner_id' => $this->user->id, 'title' => 'a']));

        $this->assertCount(1, $batches);
    }

    #[Test]
    public function muted_models_record_nothing_and_the_table_helper_reaches_everyone(): void
    {
        $batches = Reactive::capturingInvalidations(function (): void {
            Note::withoutReactiveEvents(function (): void {
                Note::query()->create(['owner_id' => $this->user->id, 'title' => 'bulk']);
                $this->assertTrue(Note::reactiveEventsMuted());
            });

            $this->assertFalse(Note::reactiveEventsMuted());
            Invalidate::table('notes');
        });

        $this->assertCount(1, $batches);
        $this->assertTrue($batches[0]->changeObjects()[0]->isTableWide());
        $this->assertNotNull(Note::query()->where('title', 'bulk')->first()?->id, 'the ULID hook still ran');
    }

    #[Test]
    public function a_delete_carries_the_before_values_so_predicates_still_match(): void
    {
        $note = Note::query()->create(['owner_id' => $this->user->id, 'title' => 'gone']);
        Reactive::fake()->subscribe($this->user, 'notes.list', ['ownerId' => $this->user->id]);

        $note->delete();

        Reactive::assertPushed('notes.list', fn (mixed $result) => $result === []);
    }

    #[Test]
    public function bursts_coalesce_and_a_later_commit_requeues(): void
    {
        $subscription = Reactive::fake()->subscribe($this->user, 'notes.list', ['ownerId' => $this->user->id]);
        $registry = app(Registry::class);

        // Simulate a recompute still queued: the debounce marker is held.
        // It is keyed on the computation, so every tab watching the same
        // question coalesces onto the one marker.
        $this->assertTrue($registry->debounce($subscription->computationKey, 60_000));
        Note::query()->create(['owner_id' => $this->user->id, 'title' => 'while-held']);

        Reactive::assertNotPushed();
        $this->assertSame(1, app(Metrics::class)->snapshot()['metrics']['coalesced']);

        // The recompute runs, releases the marker first, and pushes.
        (new RecomputeComputation($subscription->computationKey, 1))->handle($registry, app(Metrics::class), app(Catalog::class), app(QueryRunner::class), app(Pusher::class));
        Reactive::assertPushed('notes.list');

        Note::query()->create(['owner_id' => $this->user->id, 'title' => 'after']);
        $this->assertCount(2, Reactive::pushed('notes.list'));
        Reactive::assertOrdered();
    }

    #[Test]
    public function an_out_of_order_recompute_is_discarded(): void
    {
        $subscription = Reactive::fake()->subscribe($this->user, 'notes.list', ['ownerId' => $this->user->id]);
        $registry = app(Registry::class);
        $registry->updateComputation($subscription->computationKey, 'stale', 'null', 10);

        (new RecomputeComputation($subscription->computationKey, 3))->handle($registry, app(Metrics::class), app(Catalog::class), app(QueryRunner::class), app(Pusher::class));

        Reactive::assertNotPushed();
        $this->assertSame(1, app(Metrics::class)->snapshot()['metrics']['discarded']);
    }
}
