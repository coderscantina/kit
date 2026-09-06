<?php

declare(strict_types=1);

namespace Kit\Reactive\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Http\Requests\MutateRequest;
use Kit\Reactive\Http\Requests\SubscribeRequest;
use Kit\Reactive\Http\Requests\UnsubscribeRequest;
use Kit\Reactive\Query;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Registry\Computation;
use Kit\Reactive\Runtime\MutationRunner;
use Kit\Reactive\Runtime\QueryRunner;
use Kit\Reactive\Runtime\Subscriber;
use Spatie\LaravelData\Data;

/**
 * The /rq/* transport (§4.5).
 */
final class ReactiveController
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Metrics $metrics,
        private readonly Catalog $catalog,
        private readonly QueryRunner $queries,
        private readonly MutationRunner $mutations,
        private readonly Subscriber $subscriber,
    ) {}

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $this->guardArgsSize($request);
        $user = $this->user($request);
        $name = $request->string('query')->toString();
        $query = $this->resolveQuery($name);

        abort_if(
            $this->registry->countForUser($this->userId($user)) >= (int) config('reactive.max_subscriptions_per_user', 200),
            429,
            'Subscription limit reached.',
        );

        $outcome = $this->subscriber->subscribe($query, $name, $user, $this->args($request));

        return response()->json([
            'subscriptionId' => $outcome->subscription->id,
            'result' => $outcome->computation->decodedResult(),
            'mutationId' => $outcome->computation->lastMutationId,
        ]);
    }

    public function unsubscribe(UnsubscribeRequest $request): Response
    {
        $id = $request->string('subscriptionId')->toString();
        $subscription = $this->registry->get($id);

        // Someone else's id is indistinguishable from an unknown one.
        if ($subscription !== null && $subscription->userId === $this->userId($this->user($request))) {
            $this->registry->delete($id);
        }

        return response()->noContent();
    }

    public function mutate(MutateRequest $request): JsonResponse
    {
        $this->guardArgsSize($request);
        $class = $this->catalog->mutation($request->string('mutation')->toString());

        abort_if($class === null, 404, 'Unknown mutation.');

        $outcome = $this->mutations->run(app($class), $this->user($request), $this->args($request));
        $this->metrics->increment('mutations');

        return response()->json(['result' => $outcome->result, 'mutationId' => $outcome->mutationId]);
    }

    /**
     * One-shot, no subscription: prefetches, scripts, and the fetch the
     * client does when a push arrived without an inline result.
     *
     * Authorization is never skipped, but the work is: when someone already
     * subscribes to this query with these args, the stored result answers,
     * carrying the watermark the last push carried. That is what keeps the
     * large-result fallback from re-running an expensive query per client.
     */
    public function query(SubscribeRequest $request): JsonResponse
    {
        $this->guardArgsSize($request);
        $name = $request->string('query')->toString();
        $query = $this->resolveQuery($name);
        $user = $this->user($request);

        $args = $this->queries->validate($query, $this->args($request));
        $this->queries->authorize($query, $user, $args);

        $computation = $this->registry->computation(Computation::keyFor($name, $args->toArray()));

        if ($computation !== null) {
            $this->metrics->increment('cached');

            return response()->json(['result' => $computation->decodedResult(), 'mutationId' => $computation->lastMutationId]);
        }

        $mutationId = $this->registry->currentMutationId();
        $outcome = $this->queries->compute($query, $args);

        return response()->json(['result' => $outcome->result, 'mutationId' => $mutationId]);
    }

    public function health(): JsonResponse
    {
        $stats = $this->registry->stats();
        ['metrics' => $metrics, 'latencies' => $latencies] = $this->metrics->snapshot();
        $recomputes = $metrics['recomputes'] ?? 0;
        $subscriptions = $stats['subscriptions'];
        $computations = $stats['computations'];
        sort($latencies);

        return response()->json([
            'version' => (string) config('app.version'),
            'registry' => [
                'driver' => (string) config('reactive.registry'),
                // From the index sets, so an upper bound until reactive:gc runs.
                'subscriptions' => $subscriptions,
                'computations' => $computations,
                // Subscribers per computation: how much work the sharing saves.
                'sharing_ratio' => $computations > 0 ? round($subscriptions / $computations, 2) : 0,
                'users' => $stats['users'],
                'queries' => count($this->catalog->queries()),
                'mutations' => count($this->catalog->mutations()),
            ],
            'worker' => [
                'recomputes' => $recomputes,
                // Recomputed inside the writer's request rather than on the queue.
                'inline' => $metrics['inline'] ?? 0,
                'unchanged_ratio' => $recomputes > 0 ? round(($metrics['unchanged'] ?? 0) / $recomputes, 3) : 0,
                'discarded' => $metrics['discarded'] ?? 0,
                'coalesced' => $metrics['coalesced'] ?? 0,
                'revoked' => $metrics['revoked'] ?? 0,
                'lock_timeout' => $metrics['lock_timeout'] ?? 0,
                'invalidations' => $metrics['invalidations'] ?? 0,
                'p95_ms' => $latencies === [] ? null : $latencies[(int) floor(0.95 * (count($latencies) - 1))],
            ],
        ]);
    }

    /**
     * @return Query<Data>
     */
    private function resolveQuery(string $name): Query
    {
        $class = $this->catalog->query($name);

        abort_if($class === null, 404, 'Unknown query.');

        return app($class);
    }

    /**
     * @return array<string, mixed>
     */
    private function args(Request $request): array
    {
        /** @var array<string, mixed> $args */
        $args = $request->input('args', []);

        return $args;
    }

    private function guardArgsSize(Request $request): void
    {
        abort_if(strlen($request->getContent()) > (int) config('reactive.max_args_bytes', 16384), 413, 'Args payload too large.');
    }

    private function user(Request $request): Authenticatable
    {
        $user = $request->user();

        abort_if($user === null, 401);

        return $user;
    }

    private function userId(Authenticatable $user): string
    {
        return (string) $user->getAuthIdentifier();
    }
}
