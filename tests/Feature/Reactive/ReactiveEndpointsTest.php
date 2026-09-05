<?php

declare(strict_types=1);

namespace Tests\Feature\Reactive;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
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
        $this->assertSame(['notes'], $subscription->tables);
        $this->assertSame('owner_id', $subscription->deps[0]->column);
        $this->assertSame([], app(Registry::class)->idsForTable('notes'), 'a predicate keeps it out of the table-level set');
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
        $this->assertSame($expected, app(Registry::class)->get($subscription->id)?->lastMutationId);
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
        $this->assertSame(1, app(Registry::class)->stats()['metrics']['unchanged']);
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

        $this->post('/auth/logout');
        $this->getJson('/rq/health')
            ->assertOk()
            ->assertJsonPath('registry.driver', 'array')
            ->assertJsonPath('registry.queries', 2)
            ->assertJsonPath('worker.recomputes', 0)
            ->assertJsonStructure(['version', 'worker' => ['p95_ms', 'unchanged_ratio']]);
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
