<?php

declare(strict_types=1);

namespace Tests\Feature\Reactive;

use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Http\Controllers\ReactiveController;
use Kit\Reactive\Runtime\MutationRunner;
use Kit\Reactive\Runtime\QueryRunner;
use Kit\Reactive\Testing\InteractsWithReactive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Reactive\Note;
use Tests\Fixtures\Reactive\ReactiveFixtures;
use Tests\TestCase;

#[CoversClass(ReactiveController::class)]
#[CoversClass(QueryRunner::class)]
#[CoversClass(MutationRunner::class)]
final class ReactiveEndpointsTest extends TestCase
{
    use InteractsWithReactive;
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        ReactiveFixtures::install();
        Reactive::fake();
        $this->user = $this->createAndActAs(role: 'member');
    }

    #[Test]
    public function subscribe_returns_the_result_the_id_and_the_watermark_and_registers_deps(): void
    {
        Note::query()->create(['owner_id' => $this->user->id, 'title' => 'first']);
        $base = app(Registry::class)->currentMutationId();

        $response = $this->postJson('/rq/subscribe', ['query' => 'notes.list', 'args' => ['ownerId' => $this->user->id]])
            ->assertOk()
            ->assertJsonPath('result.0.title', 'first')
            ->assertJsonPath('mutationId', $base);

        $subscription = app(Registry::class)->get($response->json('subscriptionId'));
        $this->assertNotNull($subscription);
        $this->assertSame($this->user->id, $subscription->userId);

        $computation = Reactive::fake()->computation($subscription);
        $this->assertSame(['notes'], $computation->tables);
        $this->assertSame('owner_id', $computation->deps[0]->column);
        $this->assertSame([], app(Registry::class)->keysForTable('notes'), 'a predicate keeps it out of the table-level set');
    }

    #[Test]
    public function the_pipeline_answers_422_403_404_in_that_order(): void
    {
        $this->postJson('/rq/subscribe', ['query' => 'notes.list', 'args' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ownerId');

        $this->postJson('/rq/subscribe', ['query' => 'notes.list', 'args' => ['ownerId' => 'someone-else']])
            ->assertForbidden();

        $this->postJson('/rq/subscribe', ['query' => 'nope.list'])->assertNotFound();
        $this->postJson('/rq/mutate', ['mutation' => 'nope.create'])->assertNotFound();
        $this->postJson('/rq/query', ['query' => 'notes.all'])->assertForbidden();
    }

    #[Test]
    public function anonymous_and_roleless_users_are_kept_out(): void
    {
        $this->post('/auth/logout');
        $this->postJson('/rq/subscribe', ['query' => 'notes.all'])->assertUnauthorized();

        $this->actingAs(User::factory()->create());
        $this->postJson('/rq/subscribe', ['query' => 'notes.all'])->assertForbidden();
    }

    #[Test]
    public function a_mutation_commits_takes_a_mutation_id_and_pushes_to_subscribers(): void
    {
        $subscription = $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);
        $expected = app(Registry::class)->currentMutationId() + 1;

        $this->mutate('notes.create', ['title' => 'hello'])
            ->assertOk()
            ->assertJsonPath('result.title', 'hello')
            ->assertJsonPath('mutationId', $expected);

        $this->assertDatabaseHas('notes', ['title' => 'hello']);

        Reactive::assertPushed('notes.list', fn (mixed $result, int $mutationId) => $result[0]['title'] === 'hello' && $mutationId === $expected);

        $computation = Reactive::fake()->computation($subscription);
        $this->assertSame($expected, $computation->lastMutationId);
        // The result is stored, not just its hash, so the next subscriber and
        // the large-result fallback do not re-run the query.
        $this->assertSame('hello', $computation->decodedResult()[0]['title']);
    }

    #[Test]
    public function a_change_outside_the_predicate_does_not_push(): void
    {
        $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);

        Note::query()->create(['owner_id' => 'someone-else', 'title' => 'theirs']);

        Reactive::assertNotPushed();
    }

    #[Test]
    public function an_unchanged_result_is_not_pushed(): void
    {
        $note = Note::query()->create(['owner_id' => $this->user->id, 'title' => 'same']);
        $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);

        $this->mutate('notes.rename', ['id' => $note->id, 'title' => 'same'])->assertOk();

        Reactive::assertNotPushed();
        $this->assertSame(1, app(Metrics::class)->snapshot()['metrics']['unchanged']);
    }

    #[Test]
    public function a_failing_mutation_rolls_back_and_pushes_nothing(): void
    {
        $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);

        $this->withoutExceptionHandling();

        try {
            $this->mutate('notes.fail');
            $this->fail('expected the mutation to throw');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertDatabaseMissing('notes', ['title' => 'doomed']);
        Reactive::assertNotPushed();
    }

    #[Test]
    public function validation_and_authorization_failures_do_not_touch_the_database(): void
    {
        $this->mutate('notes.create', ['title' => ''])->assertUnprocessable();

        $unverified = User::factory()->unverified()->create();
        $this->assignRole($unverified, 'member');
        $this->actingAs($unverified);

        $this->mutate('notes.create', ['title' => 'x'])->assertForbidden();
        $this->assertSame(0, Note::query()->count());
    }

    #[Test]
    public function a_subscriber_whose_access_is_withdrawn_is_revoked(): void
    {
        $root = User::factory()->root()->create();
        $subscription = $this->actingAsSubscriber($root, 'notes.all');

        $root->is_root = false;
        $root->save();

        Note::query()->create(['owner_id' => 'x', 'title' => 'trigger']);

        Reactive::assertRevoked($subscription->id);
        $this->assertNull(app(Registry::class)->get($subscription->id));
    }

    #[Test]
    public function unsubscribe_only_removes_the_callers_own_subscription(): void
    {
        $mine = $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);
        $other = User::factory()->create();
        $theirs = Reactive::fake()->subscribe($other, 'notes.list', ['ownerId' => $other->id]);

        $this->postJson('/rq/unsubscribe', ['subscriptionId' => $theirs->id])->assertNoContent();
        $this->assertNotNull(app(Registry::class)->get($theirs->id));

        $this->postJson('/rq/unsubscribe', ['subscriptionId' => $mine->id])->assertNoContent();
        $this->assertNull(app(Registry::class)->get($mine->id));
    }

    #[Test]
    public function logout_purges_every_subscription_of_the_user(): void
    {
        $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);
        $this->assertSame(1, app(Registry::class)->countForUser($this->user->id));

        $this->post('/auth/logout')->assertNoContent();

        $this->assertSame(0, app(Registry::class)->countForUser($this->user->id));
    }

    #[Test]
    public function limits_are_enforced(): void
    {
        config(['reactive.max_subscriptions_per_user' => 1, 'reactive.max_args_bytes' => 200]);

        $this->postJson('/rq/subscribe', ['query' => 'notes.list', 'args' => ['ownerId' => $this->user->id]])->assertOk();
        $this->postJson('/rq/subscribe', ['query' => 'notes.list', 'args' => ['ownerId' => $this->user->id]])->assertStatus(429);

        $this->postJson('/rq/query', ['query' => 'notes.list', 'args' => ['ownerId' => $this->user->id, 'pad' => str_repeat('x', 300)]])
            ->assertStatus(413);
    }

    #[Test]
    public function query_is_one_shot_and_health_reports_metrics(): void
    {
        $this->postJson('/rq/query', ['query' => 'notes.list', 'args' => ['ownerId' => $this->user->id]])
            ->assertOk()
            ->assertJsonPath('result', [])
            ->assertJsonPath('mutationId', app(Registry::class)->currentMutationId());

        $this->assertSame(0, app(Registry::class)->stats()['subscriptions']);

        $health = $this->getJson('/rq/health')
            ->assertOk()
            ->assertJsonPath('registry.driver', 'array')
            ->assertJsonPath('worker.recomputes', 0)
            ->assertJsonStructure(['version', 'worker' => ['p95_ms', 'unchanged_ratio']]);

        // At least the two fixture queries; a generated feature adds its own.
        $this->assertGreaterThanOrEqual(2, $health->json('registry.queries'));
    }

    #[Test]
    public function health_is_not_public(): void
    {
        $this->post('/auth/logout');

        $this->getJson('/rq/health')->assertUnauthorized();
    }

    #[Test]
    public function two_users_asking_the_same_question_share_one_computation_and_one_recompute(): void
    {
        $first = User::factory()->root()->create();
        $second = User::factory()->root()->create();

        $a = Reactive::fake()->subscribe($first, 'notes.all');
        $b = Reactive::fake()->subscribe($second, 'notes.all');

        $registry = app(Registry::class);
        $metrics = app(Metrics::class)->snapshot()['metrics'];

        $this->assertSame($a->computationKey, $b->computationKey, 'same query, same args, same computation');
        $this->assertSame(1, $metrics['computed'], 'the query ran once');
        $this->assertSame(1, $metrics['shared'], 'the second subscriber read the stored result');
        $this->assertSame(2, $registry->stats()['subscriptions']);
        $this->assertSame(1, $registry->stats()['computations']);

        Note::query()->create(['owner_id' => 'x', 'title' => 'shared']);

        // One recompute, two pushes: the cost of a change is per question,
        // the delivery is per subscriber.
        $this->assertSame(1, app(Metrics::class)->snapshot()['metrics']['recomputes']);
        $this->assertCount(2, Reactive::pushed('notes.all'));
        Reactive::assertPushed('notes.all', fn (mixed $result) => $result[0]['title'] === 'shared');
    }

    #[Test]
    public function a_revoked_subscriber_is_dropped_while_the_others_keep_their_push(): void
    {
        $staying = User::factory()->root()->create();
        $leaving = User::factory()->root()->create();

        $kept = Reactive::fake()->subscribe($staying, 'notes.all');
        $dropped = Reactive::fake()->subscribe($leaving, 'notes.all');

        $leaving->is_root = false;
        $leaving->save();

        Note::query()->create(['owner_id' => 'x', 'title' => 'trigger']);

        Reactive::assertRevoked($dropped->id);
        $this->assertNull(app(Registry::class)->get($dropped->id));

        $pushes = Reactive::pushed('notes.all');
        $this->assertCount(1, $pushes);
        $this->assertSame($kept->id, $pushes[0]['subscription']->id);
    }

    #[Test]
    public function the_one_shot_query_answers_from_the_stored_result(): void
    {
        Note::query()->create(['owner_id' => $this->user->id, 'title' => 'stored']);
        $subscription = $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);
        $computation = Reactive::fake()->computation($subscription);

        // This is the path a client takes when a push arrived without an
        // inline result. It must not re-run the query per client.
        $this->postJson('/rq/query', ['query' => 'notes.list', 'args' => ['ownerId' => $this->user->id]])
            ->assertOk()
            ->assertJsonPath('result.0.title', 'stored')
            ->assertJsonPath('mutationId', $computation->lastMutationId);

        $snapshot = app(Metrics::class)->snapshot()['metrics'];
        $this->assertSame(1, $snapshot['cached']);
        $this->assertSame(1, $snapshot['computed'], 'the subscribe was the only run');
    }

    #[Test]
    public function the_one_shot_query_still_authorizes_before_serving_a_stored_result(): void
    {
        $owner = User::factory()->root()->create();
        Reactive::fake()->subscribe($owner, 'notes.all');

        // A stored result is not a bypass: the caller's own authorize() runs.
        $this->postJson('/rq/query', ['query' => 'notes.all'])->assertForbidden();
    }

    #[Test]
    public function losing_an_ability_drops_the_live_subscriptions_at_once(): void
    {
        $subscription = $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);

        // Waiting for the next changed result would leave a subscription the
        // user may no longer read; busting the ability cache drops it now.
        app(AuthorizationService::class)->invalidateUser($this->user);

        $this->assertNull(app(Registry::class)->get($subscription->id));
        $this->assertSame(0, app(Registry::class)->countForUser($this->user->id));
    }

    #[Test]
    public function the_channel_authorizes_only_the_owner(): void
    {
        $mine = $this->actingAsSubscriber($this->user, 'notes.list', ['ownerId' => $this->user->id]);
        $other = User::factory()->create();

        // The null broadcaster in tests never denies, so exercise the
        // registered channel callback itself.
        $callback = Broadcast::getChannels()->get('subscription.{id}');
        $this->assertIsCallable($callback);

        $this->assertTrue($callback($this->user, $mine->id));
        $this->assertFalse($callback($other, $mine->id));
        $this->assertFalse($callback($this->user, 'unknown'));
    }
}
