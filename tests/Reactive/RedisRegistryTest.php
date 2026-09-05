<?php

declare(strict_types=1);

namespace Tests\Reactive;

use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\Redis;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Dep;
use Kit\Reactive\Registry\Computation;
use Kit\Reactive\Registry\RedisRegistry;
use Kit\Reactive\Registry\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(RedisRegistry::class)]
final class RedisRegistryTest extends ReactiveTestCase
{
    #[Test]
    public function it_stores_the_computation_its_watchers_and_the_dep_sets_with_a_ttl(): void
    {
        $registry = app(Registry::class);
        $this->assertInstanceOf(RedisRegistry::class, $registry);

        $registry->putComputation($this->computation('c1', ['notes', 'users'], [Dep::eq('notes', 'owner_id', 'u1')]));
        $registry->put($this->subscription('s1', 'c1', 'u1'));

        $stored = $registry->computation('c1');
        $this->assertSame('notes.list', $stored?->query);
        $this->assertSame(['ownerId' => 'u1'], $stored?->args);
        $this->assertSame('owner_id', $stored?->deps[0]->column);
        $this->assertSame([['title' => 'first']], $stored?->decodedResult());

        $this->assertSame('c1', $registry->get('s1')?->computationKey);
        $this->assertSame(['s1'], $registry->subscribersOf('c1'));
        $this->assertSame(['s1'], $registry->idsForUser('u1'));

        // Dep sets hold computation keys, not subscription ids.
        $this->assertSame(['c1'], $registry->keysForTable('users'));
        $this->assertSame([], $registry->keysForTable('notes'));
        $this->assertSame(['c1'], $registry->keysForPredicate('notes', 'owner_id', 'u1'));
        $this->assertSame(['owner_id:u1'], $registry->predicateKeys('notes'));

        foreach (['rq:comp:c1', 'rq:comp:c1:subs', 'rq:sub:s1'] as $key) {
            $ttl = Redis::connection()->ttl($key);
            $this->assertGreaterThan(3500, $ttl, "{$key} has no TTL");
            $this->assertLessThanOrEqual(3600, $ttl);
        }
    }

    #[Test]
    public function many_subscribers_share_one_computation_and_the_last_one_out_drops_it(): void
    {
        $registry = app(Registry::class);
        $registry->putComputation($this->computation('c1', ['notes'], []));

        foreach (['s1', 's2', 's3'] as $index => $id) {
            $registry->put($this->subscription($id, 'c1', "u{$index}"));
        }

        $this->assertCount(3, $registry->subscribersOf('c1'));
        $this->assertSame(['c1'], $registry->keysForTable('notes'));
        $this->assertSame(3, $registry->stats()['subscriptions']);
        $this->assertSame(1, $registry->stats()['computations'], 'one query run serves all three');

        $registry->delete('s1');
        $registry->delete('s2');
        $this->assertNotNull($registry->computation('c1'), 'still watched');

        $registry->delete('s3');
        $this->assertNull($registry->computation('c1'));
        $this->assertSame([], $registry->keysForTable('notes'));
        $this->assertSame([], $registry->subscribersOf('c1'));
    }

    #[Test]
    public function updating_a_result_refreshes_the_ttl_of_the_whole_group(): void
    {
        $registry = app(Registry::class);
        $registry->putComputation($this->computation('c1', ['notes'], []));
        $registry->put($this->subscription('s1', 'c1', 'u1'));

        Redis::connection()->expire('rq:sub:s1', 10);
        Redis::connection()->expire('rq:comp:c1', 10);

        $registry->updateComputation('c1', 'h2', '[{"title":"second"}]', 7);

        $stored = $registry->computation('c1');
        $this->assertNotNull($stored);
        $this->assertSame('h2', $stored->resultHash);
        $this->assertSame(7, $stored->lastMutationId);
        $this->assertSame([['title' => 'second']], $stored->decodedResult());
        $this->assertGreaterThan(3500, Redis::connection()->ttl('rq:sub:s1'));
        $this->assertGreaterThan(3500, Redis::connection()->ttl('rq:comp:c1'));
    }

    #[Test]
    public function the_counter_is_monotonic_and_debounce_is_atomic(): void
    {
        $registry = app(Registry::class);

        $this->assertSame(0, $registry->currentMutationId());
        $this->assertSame(1, $registry->nextMutationId());
        $this->assertSame(2, $registry->nextMutationId());
        $this->assertSame(2, $registry->currentMutationId());

        $this->assertTrue($registry->debounce('c1', 200));
        $this->assertFalse($registry->debounce('c1', 200));
        $this->assertGreaterThan(0, Redis::connection()->pttl('rq:debounce:c1'));
        $registry->releaseDebounce('c1');
        $this->assertTrue($registry->debounce('c1', 200));
    }

    #[Test]
    public function gc_prunes_expired_computations_orphaned_subscriptions_and_unwatched_work(): void
    {
        $registry = app(Registry::class);

        $registry->putComputation($this->computation('c1', ['notes'], [Dep::eq('notes', 'x', '1')]));
        $registry->put($this->subscription('s1', 'c1', 'u1'));

        // Simulate the TTL backstop firing on the computation without a
        // cleanup event: its dep entries and its subscription are stranded.
        Redis::connection()->del(['rq:comp:c1']);
        $this->assertSame(['c1'], $registry->keysForPredicate('notes', 'x', '1'));

        $this->assertGreaterThanOrEqual(2, $registry->gc());
        $this->assertSame([], $registry->keysForPredicate('notes', 'x', '1'));
        $this->assertNull($registry->get('s1'));
        $this->assertSame(0, $registry->countForUser('u1'));

        // A computation whose subscribers all went away is work nobody reads.
        $registry->putComputation($this->computation('c2', ['notes'], []));
        $this->assertSame(1, $registry->gc());
        $this->assertNull($registry->computation('c2'));
    }

    #[Test]
    public function purge_removes_every_subscription_of_a_user(): void
    {
        $registry = app(Registry::class);
        $registry->putComputation($this->computation('c1', ['notes'], []));
        $registry->put($this->subscription('s1', 'c1', 'u2'));

        $registry->purgeUser('u2');

        $this->assertNull($registry->get('s1'));
        $this->assertNull($registry->computation('c1'));
        $this->assertSame(0, $registry->stats()['subscriptions']);
        $this->assertSame(0, $registry->stats()['computations']);
    }

    #[Test]
    public function the_lock_serialises_callers(): void
    {
        $registry = app(Registry::class);
        $order = [];

        $registry->withLock('c1', function () use (&$order): void {
            $order[] = 'outer';
            $this->assertSame(1, (int) Redis::connection()->exists('rq:lock:c1'));
        });

        $this->assertSame(['outer'], $order);
        $this->assertNull(Redis::connection()->get('rq:lock:c1'));
    }

    #[Test]
    public function a_caller_that_cannot_take_the_lock_runs_nothing_and_leaves_the_owner_alone(): void
    {
        // A 50 ms wait rather than the configured 5 s: the point is what
        // happens after the deadline, not how long it is.
        $registry = new RedisRegistry(app(RedisFactory::class), 'default', 3600, 50);

        Redis::connection()->set('rq:lock:c1', 'another-worker');
        Redis::connection()->expire('rq:lock:c1', 60);

        $ran = false;
        $result = $registry->withLock('c1', function () use (&$ran): string {
            $ran = true;

            return 'pushed';
        });

        $this->assertNull($result, 'null is how the caller learns it never held the lock');
        $this->assertFalse($ran, 'the callback must not run outside the lock');
        $this->assertSame('another-worker', Redis::connection()->get('rq:lock:c1'), 'the owner still holds it');
    }

    /**
     * @param  array<int, string>  $tables
     * @param  array<int, Dep>  $deps
     */
    private function computation(string $key, array $tables, array $deps): Computation
    {
        return new Computation(
            key: $key,
            query: 'notes.list',
            args: ['ownerId' => 'u1'],
            resultHash: 'h1',
            result: '[{"title":"first"}]',
            lastMutationId: 0,
            createdAt: time(),
            tables: $tables,
            deps: $deps,
        );
    }

    private function subscription(string $id, string $computationKey, string $userId): Subscription
    {
        return new Subscription($id, $computationKey, 'notes.list', ['ownerId' => 'u1'], $userId, time());
    }
}
