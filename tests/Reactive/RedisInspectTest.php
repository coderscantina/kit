<?php

declare(strict_types=1);

namespace Tests\Reactive;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Registry\Computation;
use Kit\Reactive\Registry\LastRecompute;
use Kit\Reactive\Registry\RedisRegistry;
use Kit\Reactive\Registry\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

/**
 * What `reactive:inspect` reads from Redis: the computation index and the
 * recompute trace stored next to the result.
 */
#[CoversClass(RedisRegistry::class)]
final class RedisInspectTest extends ReactiveTestCase
{
    #[Test]
    public function the_recompute_trace_round_trips_and_an_expired_computation_is_not_listed(): void
    {
        $registry = app(Registry::class);

        foreach (['c1', 'c2'] as $key) {
            $registry->putComputation(new Computation($key, 'notes.list', ['ownerId' => 'u1'], 'h1', '[]', 1, time(), ['notes']));
            $registry->put(new Subscription("s-{$key}", $key, 'notes.list', ['ownerId' => 'u1'], 'u1', time()));
        }

        $this->assertNull($registry->computation('c1')?->lastRecompute);

        $registry->updateComputation('c1', 'h2', '[{"title":"a"}]', 2, new LastRecompute(1_700_000_000, 3.25, LastRecompute::INLINE, true));
        $registry->touchComputation('c1', 3, new LastRecompute(1_700_000_001, 1.5, LastRecompute::QUEUE, false));

        $last = $registry->computation('c1')?->lastRecompute;
        $this->assertNotNull($last);
        $this->assertSame([1_700_000_001, 1.5, 'queue', false], [$last->at, $last->queryMs, $last->via, $last->changed]);

        // The TTL fired; the index still names it until gc.
        Redis::connection()->del(['rq:comp:c2']);

        $this->assertSame(['c1'], array_map(fn (Computation $c) => $c->key, $registry->computations()));

        Artisan::call('reactive:inspect', ['--json' => true]);
        /** @var array{registry: string, queries: array<int, array{computations: array<int, array{key: string, last_recompute: array{via: string}}>}>} $document */
        $document = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('redis', $document['registry']);
        $this->assertSame('c1', $document['queries'][0]['computations'][0]['key']);
        $this->assertSame('queue', $document['queries'][0]['computations'][0]['last_recompute']['via']);
    }
}
