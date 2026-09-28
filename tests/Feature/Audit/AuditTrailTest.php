<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Models\Concerns\Auditable;
use App\Models\User;
use App\Queries\Audit\RecordHistory;
use App\Services\Audit\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Kit\Reactive\Facades\Reactive;
use Kit\Reactive\Testing\InteractsWithReactive;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[CoversClass(Auditable::class)]
#[CoversClass(AuditLog::class)]
#[CoversClass(RecordHistory::class)]
final class AuditTrailTest extends TestCase
{
    use InteractsWithReactive;
    use RefreshDatabase;

    #[Test]
    public function an_update_records_before_and_after_with_the_actor(): void
    {
        $admin = $this->createAndActAs(role: 'admin');
        $target = User::factory()->create(['name' => 'Ada']);

        $target->name = 'Grace';
        $target->save();

        $entry = AuditEntry::query()->where('subject_id', $target->id)->where('event', 'updated')->sole();

        $this->assertSame($admin->id, $entry->actor_id);
        $this->assertSame(['name' => ['before' => 'Ada', 'after' => 'Grace']], $entry->changes);
    }

    #[Test]
    public function hidden_and_encrypted_fields_are_listed_without_their_values(): void
    {
        $user = User::factory()->create();

        $user->password = 'a-new-password';
        $user->two_factor_secret = 'JBSWY3DPEHPK3PXP';
        $user->save();

        $changes = AuditEntry::query()->where('subject_id', $user->id)->where('event', 'updated')->sole()->changes;

        $this->assertSame(['redacted' => true], $changes['password']);
        $this->assertSame(['redacted' => true], $changes['two_factor_secret']);
        $this->assertStringNotContainsString('JBSWY3DPEHPK3PXP', (string) json_encode($changes));
    }

    #[Test]
    public function a_change_to_an_excluded_column_writes_no_entry(): void
    {
        $user = User::factory()->create();

        $user->last_login_at = now();
        $user->save();

        $this->assertSame(0, AuditEntry::query()->where('subject_id', $user->id)->where('event', 'updated')->count());
    }

    #[Test]
    public function a_rolled_back_write_leaves_no_entry(): void
    {
        $user = User::factory()->create();

        try {
            DB::transaction(function () use ($user): void {
                $user->name = 'Never saved';
                $user->save();

                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame(0, AuditEntry::query()->where('subject_id', $user->id)->where('event', 'updated')->count());
    }

    #[Test]
    public function the_history_is_live_for_whoever_may_view_the_record(): void
    {
        $admin = $this->createAndActAs(role: 'admin');
        $target = User::factory()->create(['name' => 'Ada']);
        Reactive::fake();

        $this->actingAsSubscriber($admin, 'audit.history', ['type' => 'users', 'id' => $target->id]);

        $target->name = 'Grace';
        $target->save();

        Reactive::assertPushed(
            'audit.history',
            fn (mixed $result): bool => $result[0]['event'] === 'updated'
                && $result[0]['actorName'] === $admin->name
                && $result[0]['changes'][0] === ['field' => 'name', 'before' => 'Ada', 'after' => 'Grace', 'redacted' => false],
        );
    }

    #[Test]
    public function a_member_sees_their_own_history_and_nobody_elses(): void
    {
        $member = $this->createAndActAs(role: 'member');
        $other = User::factory()->create();

        $this->postJson('/rq/query', ['query' => 'audit.history', 'args' => ['type' => 'users', 'id' => $member->id]])->assertOk();
        $this->postJson('/rq/query', ['query' => 'audit.history', 'args' => ['type' => 'users', 'id' => $other->id]])->assertForbidden();
    }

    #[Test]
    public function a_type_whose_model_is_not_auditable_is_forbidden(): void
    {
        $this->createAndActAs(role: 'owner');

        $this->postJson('/rq/query', ['query' => 'audit.history', 'args' => ['type' => 'roles', 'id' => '01K00000000000000000000000']])->assertForbidden();
    }
}
