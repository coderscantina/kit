<?php

declare(strict_types=1);

namespace Kit\Reactive\Facades;

use Illuminate\Support\Facades\Facade;
use Kit\Reactive\Contracts\Pusher;
use Kit\Reactive\Testing\FakePusher;
use Kit\Reactive\Testing\ReactiveFake;

/**
 * @method static \Kit\Reactive\Registry\Subscription subscribe(\Illuminate\Contracts\Auth\Authenticatable $user, string $query, array<string, mixed> $args = [])
 * @method static void assertPushed(string $query, ?callable $callback = null)
 * @method static void assertNotPushed(?string $query = null)
 * @method static void assertRevoked(string $subscriptionId)
 * @method static void assertOrdered()
 * @method static array<int, array{subscription: \Kit\Reactive\Registry\Subscription, mutationId: int, hash: string, result: mixed}> pushed(?string $query = null)
 * @method static array<int, \Kit\Reactive\Invalidation\Invalidate> capturingInvalidations(callable $callback)
 */
final class Reactive extends Facade
{
    /**
     * Capture pushes instead of broadcasting them. Idempotent within a test.
     */
    public static function fake(): ReactiveFake
    {
        $app = self::getFacadeApplication();

        if (! $app->bound(ReactiveFake::class)) {
            $pusher = new FakePusher;
            $app->instance(Pusher::class, $pusher);
            $app->instance(ReactiveFake::class, $app->make(ReactiveFake::class, ['pusher' => $pusher]));
        }

        return $app->make(ReactiveFake::class);
    }

    protected static function getFacadeAccessor(): string
    {
        return ReactiveFake::class;
    }
}
