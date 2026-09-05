<?php

declare(strict_types=1);

namespace Kit\Reactive\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Registry\Subscription;

/**
 * For tests. `actingAsSubscriber()` signs in and subscribes in one step, so
 * a mutation test reads: subscribe, mutate, assert the push.
 */
trait InteractsWithReactive
{
    /**
     * @param  array<string, mixed>  $args
     */
    protected function actingAsSubscriber(Authenticatable $user, string $query, array $args = []): Subscription
    {
        $this->actingAs($user);

        return Reactive::fake()->subscribe($user, $query, $args);
    }

    /**
     * @param  array<string, mixed>  $args
     * @return TestResponse<JsonResponse>
     */
    protected function mutate(string $mutation, array $args = []): TestResponse
    {
        return $this->postJson('/rq/mutate', ['mutation' => $mutation, 'args' => $args]);
    }
}
