<?php

declare(strict_types=1);

namespace Kit\Reactive\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Queue;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Invalidation\Invalidate;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Registry\Computation;
use Kit\Reactive\Registry\Subscription;
use Kit\Reactive\Runtime\Subscriber;
use PHPUnit\Framework\Assert;

/**
 * Test double behind `Reactive::fake()`. Pushes are captured instead of
 * broadcast; the queue stays whatever the test env set (sync), so a
 * mutation runs its invalidation and recompute inline and the push is
 * observable right after the request.
 */
final class ReactiveFake
{
    public function __construct(
        private readonly FakePusher $pusher,
        private readonly Registry $registry,
        private readonly Catalog $catalog,
        private readonly Subscriber $subscriber,
    ) {}

    /**
     * Subscribe a user to a query directly through the pipeline, without HTTP.
     * Goes through the same Subscriber the controller uses, so a second
     * subscriber to the same question shares the first one's computation here
     * exactly as it would in the app.
     *
     * @param  array<string, mixed>  $args
     */
    public function subscribe(Authenticatable $user, string $query, array $args = []): Subscription
    {
        $class = $this->catalog->query($query);

        Assert::assertNotNull($class, "Unknown reactive query '{$query}'.");

        return $this->subscriber->subscribe(app($class), $query, $user, $args)->subscription;
    }

    /**
     * The shared computation behind a subscription: its stored result and the
     * watermark the last push carried.
     */
    public function computation(Subscription $subscription): Computation
    {
        $computation = $this->registry->computation($subscription->computationKey);

        Assert::assertNotNull($computation, "Subscription {$subscription->id} has no computation.");

        return $computation;
    }

    /**
     * @return array<int, array{subscription: Subscription, mutationId: int, hash: string, result: mixed}>
     */
    public function pushed(?string $query = null): array
    {
        return array_values(array_filter(
            $this->pusher->pushes,
            fn (array $push) => $query === null || $push['subscription']->query === $query,
        ));
    }

    /**
     * @param  (callable(mixed $result, int $mutationId, Subscription $subscription): bool)|null  $callback
     */
    public function assertPushed(string $query, ?callable $callback = null): void
    {
        $pushes = $this->pushed($query);

        Assert::assertNotEmpty($pushes, "No push was sent for query '{$query}'.");

        if ($callback !== null) {
            $matching = array_filter($pushes, fn (array $push) => $callback($push['result'], $push['mutationId'], $push['subscription']));
            Assert::assertNotEmpty($matching, "A push was sent for '{$query}' but none matched the callback.");
        }
    }

    public function assertNotPushed(?string $query = null): void
    {
        Assert::assertSame([], $this->pushed($query), 'Unexpected push'.($query !== null ? " for '{$query}'" : '').'.');
    }

    public function assertRevoked(string $subscriptionId): void
    {
        $ids = array_map(fn (Subscription $s) => $s->id, $this->pusher->revoked);

        Assert::assertContains($subscriptionId, $ids, "Subscription {$subscriptionId} was not revoked.");
    }

    /**
     * Pushes are emitted in increasing mutationId order per subscription.
     */
    public function assertOrdered(): void
    {
        $last = [];

        foreach ($this->pusher->pushes as $push) {
            $id = $push['subscription']->id;
            Assert::assertGreaterThan($last[$id] ?? -1, $push['mutationId'], "Out-of-order push for subscription {$id}.");
            $last[$id] = $push['mutationId'];
        }
    }

    /**
     * Run the block with the queue faked, then return the invalidation
     * batches it dispatched; for asserting what a mutation invalidated
     * without running the recompute.
     *
     * @return array<int, Invalidate>
     */
    public function capturingInvalidations(callable $callback): array
    {
        $fake = Queue::fake([Invalidate::class]);

        // Small writes are recomputed inside the request and never become a
        // job; force the queued path so the batch is something to capture.
        $inline = config('reactive.inline_recomputes');
        config(['reactive.inline_recomputes' => 0]);

        try {
            $callback();
        } finally {
            config(['reactive.inline_recomputes' => $inline]);
        }

        $jobs = [];
        foreach ($fake->pushed(Invalidate::class) as $job) {
            $jobs[] = $job;
        }

        return $jobs;
    }
}
