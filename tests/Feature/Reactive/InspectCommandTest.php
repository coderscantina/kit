<?php

declare(strict_types=1);

namespace Tests\Feature\Reactive;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Kit\Reactive\Console\InspectCommand;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Jobs\RecomputeComputation;
use Kit\Reactive\Runtime\Recomputer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Reactive\ListNotes;
use Tests\Fixtures\Reactive\Note;
use Tests\Fixtures\Reactive\ReactiveFixtures;
use Tests\TestCase;

#[CoversClass(InspectCommand::class)]
final class InspectCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ReactiveFixtures::install();
        Reactive::fake();
    }

    #[Test]
    public function it_reports_a_shared_computation_and_the_inline_recompute_that_pushed_it(): void
    {
        $owner = User::factory()->create();
        Reactive::fake()->subscribe($owner, 'notes.list', ['ownerId' => $owner->id]);
        Reactive::fake()->subscribe(User::factory()->root()->create(), 'notes.list', ['ownerId' => $owner->id]);

        Note::query()->create(['owner_id' => $owner->id, 'title' => 'first']);

        $document = $this->inspect();
        $this->assertSame('array', $document['registry']);
        $this->assertSame(8192, $document['inline_result_bytes']);
        $this->assertCount(1, $document['queries']);

        $query = $document['queries'][0];
        $this->assertSame('notes.list', $query['name']);
        $this->assertSame(ListNotes::class, $query['class']);
        $this->assertSame(['notes'], $query['tables']);

        $computation = $query['computations'][0];
        $this->assertSame(['ownerId' => $owner->id], $computation['args']);
        $this->assertSame(2, $computation['subscribers'], 'two tabs, one computation');
        $this->assertSame($document['current_mutation_id'], $computation['last_mutation_id']);
        $this->assertSame([['table' => 'notes', 'column' => 'owner_id', 'value' => $owner->id]], $computation['reads']);
        $this->assertSame([], $computation['table_level'], 'the predicate takes notes out of the table-level set');
        $this->assertTrue($computation['result_inline']);
        $this->assertGreaterThan(0, $computation['result_bytes']);
        $this->assertSame('inline', $computation['last_recompute']['via']);
        $this->assertTrue($computation['last_recompute']['changed']);
    }

    #[Test]
    public function a_queued_recompute_with_the_same_result_says_it_pushed_nothing(): void
    {
        $owner = User::factory()->create();
        $subscription = Reactive::fake()->subscribe($owner, 'notes.list', ['ownerId' => $owner->id]);

        $before = $this->inspect()['queries'][0]['computations'][0];
        $this->assertNull($before['last_recompute'], 'never recomputed since subscribe');

        (new RecomputeComputation($subscription->computationKey, 1))->handle(app(Recomputer::class));

        $last = $this->inspect()['queries'][0]['computations'][0]['last_recompute'];
        $this->assertSame('queue', $last['via']);
        $this->assertFalse($last['changed']);
        Reactive::assertNotPushed();
    }

    #[Test]
    public function a_query_name_narrows_the_report_and_an_unknown_one_fails(): void
    {
        $user = User::factory()->root()->create();
        Reactive::fake()->subscribe($user, 'notes.list', ['ownerId' => $user->id]);
        Reactive::fake()->subscribe($user, 'notes.all');

        $this->assertSame(['notes.all', 'notes.list'], array_column($this->inspect()['queries'], 'name'));
        $this->assertSame(['notes.all'], array_column($this->inspect('notes.all')['queries'], 'name'));

        $this->artisan('reactive:inspect', ['query' => 'notes.nope'])->assertFailed();
        $this->artisan('reactive:inspect', ['query' => 'notes.list'])->expectsOutputToContain('notes.list')->assertSuccessful();
    }

    /**
     * @return array<string, mixed>
     */
    private function inspect(?string $query = null): array
    {
        $this->assertSame(0, Artisan::call('reactive:inspect', ['query' => $query, '--json' => true]));

        /** @var array<string, mixed> $document */
        $document = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        return $document;
    }
}
