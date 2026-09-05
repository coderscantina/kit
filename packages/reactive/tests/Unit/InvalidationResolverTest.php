<?php

declare(strict_types=1);

namespace Kit\Reactive\Tests\Unit;

use Kit\Reactive\Dep;
use Kit\Reactive\Invalidation\Change;
use Kit\Reactive\Invalidation\InvalidationResolver;
use Kit\Reactive\Registry\ArrayRegistry;
use Kit\Reactive\Registry\Computation;
use Kit\Reactive\Registry\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidationResolver::class)]
#[CoversClass(ArrayRegistry::class)]
#[CoversClass(Computation::class)]
#[CoversClass(Subscription::class)]
#[CoversClass(Dep::class)]
final class InvalidationResolverTest extends TestCase
{
    private ArrayRegistry $registry;

    private InvalidationResolver $resolver;

    protected function setUp(): void
    {
        $this->registry = new ArrayRegistry;
        $this->resolver = new InvalidationResolver($this->registry);

        $this->watch('table-level', ['messages'], []);
        $this->watch('channel-1', ['messages', 'channels'], [Dep::eq('messages', 'channel_id', 1)]);
        $this->watch('channel-2', ['messages'], [Dep::eq('messages', 'channel_id', 2)]);
    }

    #[Test]
    public function a_predicate_computation_is_not_in_the_table_level_set(): void
    {
        $this->assertSame(['table-level'], $this->registry->keysForTable('messages'));
        $this->assertSame(['channel-1'], $this->registry->keysForTable('channels'));
        $this->assertSame(['channel_id:1', 'channel_id:2'], $this->registry->predicateKeys('messages'));
    }

    #[Test]
    public function a_row_change_reaches_the_table_set_and_the_matching_predicate_only(): void
    {
        $change = new Change('messages', 'm1', ['channel_id' => '1', 'body' => 'a'], ['channel_id' => '1', 'body' => 'b']);

        $this->assertEqualsCanonicalizing(['table-level', 'channel-1'], $this->resolver->resolve([$change]));
    }

    #[Test]
    public function a_row_moving_between_predicate_values_reaches_both_sides(): void
    {
        $change = new Change('messages', 'm1', ['channel_id' => '1'], ['channel_id' => '2']);

        $this->assertEqualsCanonicalizing(['table-level', 'channel-1', 'channel-2'], $this->resolver->resolve([$change]));
    }

    #[Test]
    public function a_table_wide_change_reaches_every_computation_on_the_table(): void
    {
        $this->assertEqualsCanonicalizing(
            ['table-level', 'channel-1', 'channel-2'],
            $this->resolver->resolve([Change::table('messages')]),
        );
        $this->assertSame(['channel-1'], $this->resolver->resolve([Change::table('channels')]));
    }

    #[Test]
    public function two_subscribers_to_the_same_question_resolve_to_one_computation(): void
    {
        $this->registry->put($this->subscription('second-tab', 'table-level', 'u2'));

        $change = new Change('messages', 'm1', ['channel_id' => '1'], ['channel_id' => '1']);

        // Two tabs on the same question, still one key to recompute for it.
        $this->assertCount(2, $this->registry->subscribersOf('table-level'));
        $this->assertEqualsCanonicalizing(['table-level', 'channel-1'], $this->resolver->resolve([$change]));
        $this->assertSame(3, $this->registry->stats()['computations']);
        $this->assertSame(4, $this->registry->stats()['subscriptions']);
    }

    #[Test]
    public function the_last_subscriber_leaving_drops_the_computation_and_its_deps(): void
    {
        $this->registry->delete('sub-channel-2');

        $this->assertNull($this->registry->computation('channel-2'));
        $this->assertSame(['channel_id:1'], $this->registry->predicateKeys('messages'));

        $moved = new Computation('channel-1', 'q', [], 'h', 'null', 0, time(), ['messages'], [Dep::eq('messages', 'channel_id', 9)]);
        $this->registry->syncDeps($moved);

        $this->assertSame(['channel_id:9'], $this->registry->predicateKeys('messages'));
        $this->assertSame([], $this->registry->keysForTable('channels'));
        $this->assertSame(['channel-1'], $this->registry->keysForPredicate('messages', 'channel_id', '9'));
    }

    #[Test]
    public function user_sets_limits_and_purge(): void
    {
        $this->assertSame(3, $this->registry->countForUser('u1'));

        $this->registry->purgeUser('u1');

        $this->assertSame(0, $this->registry->countForUser('u1'));
        $this->assertSame([], $this->registry->keysForTable('messages'));
        $this->assertNull($this->registry->computation('table-level'));
    }

    #[Test]
    public function debounce_acquires_once_per_window(): void
    {
        $this->assertTrue($this->registry->debounce('x', 50));
        $this->assertFalse($this->registry->debounce('x', 50));

        $this->registry->releaseDebounce('x');

        $this->assertTrue($this->registry->debounce('x', 50));
    }

    /**
     * A computation with one subscriber on it, which is the shape the
     * registry is always in outside a recompute.
     *
     * @param  array<int, string>  $tables
     * @param  array<int, Dep>  $deps
     */
    private function watch(string $key, array $tables, array $deps): void
    {
        $this->registry->putComputation(new Computation($key, 'q', [], 'h', 'null', 0, time(), $tables, $deps));
        $this->registry->put($this->subscription("sub-{$key}", $key, 'u1'));
    }

    private function subscription(string $id, string $computationKey, string $userId): Subscription
    {
        return new Subscription($id, $computationKey, 'q', [], $userId, time());
    }
}
