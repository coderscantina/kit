<?php

declare(strict_types=1);

namespace Tests\Reactive;

use Illuminate\Support\Facades\Redis;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Dep;
use Kit\Reactive\Registry\RedisRegistry;
use Kit\Reactive\Registry\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(RedisRegistry::class)]
final class RedisRegistryTest extends ReactiveTestCase
{
    #[Test]
    public function it_stores_the_hash_the_user_set_and_the_dep_sets_with_a_ttl(): void
    {
        $registry = app(Registry::class);
        $this->assertInstanceOf(RedisRegistry::class, $registry);

        $registry->put(new Subscription('s1', 'notes.list', ['ownerId' => 'u1'], 'u1', 'h1', 0, time(), ['notes', 'users'], [Dep::eq('notes', 'owner_id', 'u1')]));

        $stored = $registry->get('s1');
        $this->assertSame('notes.list', $stored?->query);
        $this->assertSame(['ownerId' => 'u1'], $stored?->args);
        $this->assertSame('owner_id', $stored?->deps[0]->column);

        $this->assertSame(['s1'], $registry->idsForUser('u1'));
        $this->assertSame(['s1'], $registry->idsForTable('users'));
        $this->assertSame([], $registry->idsForTable('notes'));
        $this->assertSame(['s1'], $registry->idsForPredicate('notes', 'owner_id', 'u1'));
        $this->assertSame(['owner_id:u1'], $registry->predicateKeys('notes'));

        $ttl = Redis::connection()->ttl('rq:sub:s1');
        $this->assertGreaterThan(3500, $ttl);
        $this->assertLessThanOrEqual(3600, $ttl);
    }

    #[Test]
    public function the_counter_is_monotonic_and_debounce_is_atomic(): void
    {
        $registry = app(Registry::class);

        $this->assertSame(0, $registry->currentMutationId());
        $this->assertSame(1, $registry->nextMutationId());
        $this->assertSame(2, $registry->nextMutationId());
        $this->assertSame(2, $registry->currentMutationId());

        $this->assertTrue($registry->debounce('s1', 200));
        $this->assertFalse($registry->debounce('s1', 200));
        $this->assertGreaterThan(0, Redis::connection()->pttl('rq:debounce:s1'));
        $registry->releaseDebounce('s1');
        $this->assertTrue($registry->debounce('s1', 200));
    }

    #[Test]
    public function delete_purge_and_gc_prune_the_sets(): void
    {
        $registry = app(Registry::class);
        $registry->put(new Subscription('s1', 'q', [], 'u1', 'h', 0, time(), ['notes'], []));
        $registry->put(new Subscription('s2', 'q', [], 'u1', 'h', 0, time(), ['notes'], [Dep::eq('notes', 'x', '1')]));

        $registry->delete('s1');
        $this->assertSame([], $registry->idsForTable('notes'));
        $this->assertSame(1, $registry->countForUser('u1'));

        // Simulate the TTL backstop firing on s2 without a cleanup event.
        Redis::connection()->del(['rq:sub:s2']);
        $this->assertSame(['s2'], $registry->idsForPredicate('notes', 'x', '1'));

        $this->assertSame(2, $registry->gc());
        $this->assertSame([], $registry->idsForPredicate('notes', 'x', '1'));
        $this->assertSame(0, $registry->countForUser('u1'));

        $registry->put(new Subscription('s3', 'q', [], 'u2', 'h', 0, time(), ['notes'], []));
        $registry->purgeUser('u2');
        $this->assertNull($registry->get('s3'));
        $this->assertSame(0, $registry->stats()['subscriptions']);
    }

    #[Test]
    public function the_lock_serialises_callers(): void
    {
        $registry = app(Registry::class);
        $order = [];

        $registry->withLock('s1', function () use (&$order): void {
            $order[] = 'outer';
            $this->assertSame(1, (int) Redis::connection()->exists('rq:lock:s1'));
        });

        $this->assertSame(['outer'], $order);
        $this->assertNull(Redis::connection()->get('rq:lock:s1'));
    }
}
