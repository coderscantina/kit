<?php

declare(strict_types=1);

namespace Kit\Reactive\Tests\Unit;

use Kit\Reactive\Dep;
use Kit\Reactive\Invalidation\Change;
use Kit\Reactive\Invalidation\InvalidationResolver;
use Kit\Reactive\Registry\ArrayRegistry;
use Kit\Reactive\Registry\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidationResolver::class)]
#[CoversClass(ArrayRegistry::class)]
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

        $this->registry->put($this->subscription('table-level', ['messages'], []));
        $this->registry->put($this->subscription('channel-1', ['messages', 'channels'], [Dep::eq('messages', 'channel_id', 1)]));
        $this->registry->put($this->subscription('channel-2', ['messages'], [Dep::eq('messages', 'channel_id', 2)]));
    }

    #[Test]
    public function a_predicate_subscription_is_not_in_the_table_level_set(): void
    {
        $this->assertSame(['table-level'], $this->registry->idsForTable('messages'));
        $this->assertSame(['channel-1'], $this->registry->idsForTable('channels'));
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
    public function a_table_wide_change_reaches_every_subscriber_of_the_table(): void
    {
        $this->assertEqualsCanonicalizing(
            ['table-level', 'channel-1', 'channel-2'],
            $this->resolver->resolve([Change::table('messages')]),
        );
        $this->assertSame(['channel-1'], $this->resolver->resolve([Change::table('channels')]));
    }

    #[Test]
    public function deleting_a_subscription_prunes_its_sets_and_syncing_replaces_them(): void
    {
        $this->registry->delete('channel-2');
        $this->assertSame(['channel_id:1'], $this->registry->predicateKeys('messages'));

        $moved = $this->subscription('channel-1', ['messages'], [Dep::eq('messages', 'channel_id', 9)]);
        $this->registry->syncDeps($moved);

        $this->assertSame(['channel_id:9'], $this->registry->predicateKeys('messages'));
        $this->assertSame([], $this->registry->idsForTable('channels'));
        $this->assertSame(['channel-1'], $this->registry->idsForPredicate('messages', 'channel_id', '9'));
    }

    #[Test]
    public function user_sets_limits_and_purge(): void
    {
        $this->assertSame(3, $this->registry->countForUser('u1'));

        $this->registry->purgeUser('u1');

        $this->assertSame(0, $this->registry->countForUser('u1'));
        $this->assertSame([], $this->registry->idsForTable('messages'));
        $this->assertNull($this->registry->get('table-level'));
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
     * @param  array<int, string>  $tables
     * @param  array<int, Dep>  $deps
     */
    private function subscription(string $id, array $tables, array $deps): Subscription
    {
        return new Subscription($id, 'q', [], 'u1', 'h', 0, time(), $tables, $deps);
    }
}
