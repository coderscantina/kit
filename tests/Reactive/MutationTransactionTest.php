<?php

declare(strict_types=1);

namespace Tests\Reactive;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Mutation;
use Kit\Reactive\Runtime\MutationRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Reactive\Note;
use Tests\Fixtures\Reactive\ReactiveFixtures;

#[CoversClass(MutationRunner::class)]
final class MutationTransactionTest extends ReactiveTestCase
{
    #[Test]
    public function a_deadlock_is_retried_and_the_second_attempt_commits(): void
    {
        ReactiveFixtures::install();
        $user = User::factory()->create();

        $mutation = new #[ReactiveMutation('notes.deadlocky')] class extends Mutation
        {
            public int $attempts = 0;

            public function rules(): array
            {
                return [];
            }

            public function authorize(Authenticatable $user, array $args): void
            {
                abort_unless($user->getAuthIdentifier() !== null, 403);
            }

            public function handle(array $args): mixed
            {
                Note::query()->create(['owner_id' => 'u', 'title' => 'attempt '.++$this->attempts]);

                if ($this->attempts === 1) {
                    throw new QueryException('mysql', 'update notes', [], new \PDOException('Deadlock found when trying to get lock', 40001, null));
                }

                return $this->attempts;
            }
        };

        $level = DB::transactionLevel();
        $outcome = app(MutationRunner::class)->run($mutation, $user, []);

        $this->assertSame(2, $outcome->result);
        $this->assertSame(1, Note::query()->count(), 'the first attempt rolled back');
        $this->assertSame('attempt 2', Note::query()->firstOrFail()->title);
        $this->assertSame($level, DB::transactionLevel(), 'the runner leaves no transaction open');
        $this->assertGreaterThan(0, $outcome->mutationId);
    }

    #[Test]
    public function the_transaction_runs_at_repeatable_read_and_pushes_after_commit(): void
    {
        ReactiveFixtures::install();
        Reactive::fake();
        $user = User::factory()->create();
        $this->assignRole($user, 'member');
        Reactive::fake()->subscribe($user, 'notes.list', ['ownerId' => $user->id]);

        $expected = app(Registry::class)->currentMutationId() + 1;
        $this->actingAs($user)->postJson('/rq/mutate', ['mutation' => 'notes.create', 'args' => ['title' => 'mysql']])
            ->assertOk()
            ->assertJsonPath('mutationId', $expected);

        $level = DB::selectOne('SELECT @@transaction_isolation AS level')->level ?? DB::selectOne('SELECT @@tx_isolation AS level')->level;
        $this->assertSame('REPEATABLE-READ', $level);

        Reactive::assertPushed('notes.list', fn (mixed $result, int $mutationId) => $result[0]['title'] === 'mysql' && $mutationId === $expected);
        $this->assertSame($expected, app(Registry::class)->currentMutationId());
    }
}
