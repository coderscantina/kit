<?php

declare(strict_types=1);

namespace Kit\Reactive\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Http\Requests\MutateRequest;
use Kit\Reactive\Http\Requests\SubscribeRequest;
use Kit\Reactive\Http\Requests\UnsubscribeRequest;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Registry\Subscription;
use Kit\Reactive\Runtime\MutationRunner;
use Kit\Reactive\Runtime\QueryRunner;

/**
 * The /rq/* transport (§4.5).
 */
final class ReactiveController
{
    public function __construct(
        private readonly Registry $registry,
        private readonly Catalog $catalog,
        private readonly QueryRunner $queries,
        private readonly MutationRunner $mutations,
    ) {}

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $this->guardArgsSize($request);
        $user = $this->user($request);
        $class = $this->catalog->query($request->string('query')->toString());

        abort_if($class === null, 404, 'Unknown query.');
        abort_if(
            $this->registry->countForUser($this->userId($user)) >= (int) config('reactive.max_subscriptions_per_user', 200),
            429,
            'Subscription limit reached.',
        );

        // Counter before the query: everything committed up to this id is in
        // the result, so a push with a higher id is strictly newer.
        $mutationId = $this->registry->currentMutationId();
        $outcome = $this->queries->run(app($class), $user, $this->args($request));

        $subscription = new Subscription(
            id: (string) Str::ulid(),
            query: $request->string('query')->toString(),
            args: $outcome->args,
            userId: $this->userId($user),
            resultHash: $outcome->hash,
            lastMutationId: $mutationId,
            createdAt: time(),
            tables: $outcome->tables,
            deps: $outcome->deps,
        );

        $this->registry->put($subscription);
        $this->registry->incrementMetric('subscribes');

        return response()->json([
            'subscriptionId' => $subscription->id,
            'result' => $outcome->result,
            'mutationId' => $mutationId,
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
        $this->registry->incrementMetric('mutations');

        return response()->json(['result' => $outcome->result, 'mutationId' => $outcome->mutationId]);
    }

    /**
     * One-shot, no subscription: prefetches, scripts, and the fetch the
     * client does when a push arrived without an inline result.
     */
    public function query(SubscribeRequest $request): JsonResponse
    {
        $this->guardArgsSize($request);
        $class = $this->catalog->query($request->string('query')->toString());

        abort_if($class === null, 404, 'Unknown query.');

        $mutationId = $this->registry->currentMutationId();
        $outcome = $this->queries->run(app($class), $this->user($request), $this->args($request));

        return response()->json(['result' => $outcome->result, 'mutationId' => $mutationId]);
    }

    public function health(): JsonResponse
    {
        $stats = $this->registry->stats();
        $metrics = $stats['metrics'];
        $recomputes = $metrics['recomputes'] ?? 0;
        $latencies = $stats['latencies'];
        sort($latencies);

        return response()->json([
            'version' => (string) config('app.version'),
            'registry' => [
                'driver' => (string) config('reactive.registry'),
                'subscriptions' => $stats['subscriptions'],
                'users' => $stats['users'],
                'queries' => count($this->catalog->queries()),
                'mutations' => count($this->catalog->mutations()),
            ],
            'worker' => [
                'recomputes' => $recomputes,
                'unchanged_ratio' => $recomputes > 0 ? round(($metrics['unchanged'] ?? 0) / $recomputes, 3) : 0,
                'discarded' => $metrics['discarded'] ?? 0,
                'coalesced' => $metrics['coalesced'] ?? 0,
                'revoked' => $metrics['revoked'] ?? 0,
                'invalidations' => $metrics['invalidations'] ?? 0,
                'p95_ms' => $latencies === [] ? null : $latencies[(int) floor(0.95 * (count($latencies) - 1))],
            ],
        ]);
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
