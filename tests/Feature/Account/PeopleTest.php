<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\PeopleController;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Services\Account\PeopleDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PeopleController::class)]
#[CoversClass(PeopleDirectory::class)]
final class PeopleTest extends TestCase
{
    use RefreshDatabase;

    private function invite(string $email, string $role = 'member', ?int $expiredDaysAgo = null): Invite
    {
        return Invite::query()->create([
            'email' => $email,
            'role_id' => Role::byKey($role)->id,
            'token_hash' => Invite::hashToken('token'),
            'expires_at' => $expiredDaysAgo === null ? now()->addDays(7) : now()->subDays($expiredDaysAgo),
        ]);
    }

    /**
     * Index decoded rows by one of their fields. Typed, so the assertions do
     * not have to work through mixed.
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexBy(mixed $rows, string $field): array
    {
        $this->assertIsArray($rows);

        $indexed = [];

        foreach ($rows as $row) {
            $this->assertIsArray($row);
            $indexed[(string) $row[$field]] = $row;
        }

        return $indexed;
    }

    #[Test]
    public function accounts_and_outstanding_invitations_come_back_as_one_sorted_list(): void
    {
        $owner = $this->createAndActAs(role: 'owner');
        $owner->name = 'Ada';
        $owner->save();

        $this->assignRole(User::factory()->create(['name' => 'Zoe']), 'member');
        $this->invite('bob@example.test');

        $rows = $this->getJson('/api/people?sort=%2Bname')->assertOk()->json();

        // Invitations have no name, so they sort by the address instead.
        $this->assertSame(
            ['Ada', 'bob@example.test', 'Zoe'],
            array_map(fn (array $row): string => $row['name'] ?? $row['email'], $rows['data']),
        );

        $this->assertSame(['user', 'invite', 'user'], array_column($rows['data'], 'kind'));
        $this->assertSame(['active', 'pending', 'active'], array_column($rows['data'], 'state'));
        $this->assertSame(3, $rows['total']);
    }

    #[Test]
    public function the_counts_ignore_the_segment_so_the_tabs_do_not_move(): void
    {
        $this->createAndActAs(role: 'owner');
        $this->invite('bob@example.test');
        $this->invite('cleo@example.test');

        $all = $this->getJson('/api/people')->assertOk()->json('counts');
        $pending = $this->getJson('/api/people?status=pending')->assertOk();

        $this->assertSame(['active' => 1, 'pending' => 2, 'total' => 3], $all);
        $this->assertSame($all, $pending->json('counts'));
        $this->assertCount(2, $pending->json('data'));
    }

    #[Test]
    public function search_and_role_filters_reach_both_halves_of_the_list(): void
    {
        $this->createAndActAs(role: 'owner');
        $this->assignRole(User::factory()->create(['name' => 'Marina', 'email' => 'marina@example.test']), 'admin');
        $this->invite('marina.b@example.test', 'member');
        $this->invite('other@example.test', 'admin');

        $found = $this->getJson('/api/people?q=marina')->assertOk()->json('data');
        $this->assertSame(['marina@example.test', 'marina.b@example.test'], array_column($found, 'email'));

        $admins = $this->getJson('/api/people?role=admin')->assertOk()->json('data');
        $this->assertSame(['marina@example.test', 'other@example.test'], array_column($admins, 'email'));
    }

    #[Test]
    public function an_expired_invitation_is_still_an_invitation(): void
    {
        $this->createAndActAs(role: 'owner');
        $this->invite('stale@example.test', expiredDaysAgo: 3);

        $row = $this->indexBy($this->getJson('/api/people')->assertOk()->json('data'), 'email')['stale@example.test'];

        $this->assertSame('invite', $row['kind']);
        $this->assertSame('expired', $row['state']);
        $this->assertTrue($row['canResend'], 'An expired invitation is exactly the one worth resending.');
    }

    #[Test]
    public function the_row_carries_the_permissions_rather_than_leaving_them_to_the_client(): void
    {
        $owner = $this->createAndActAs(role: 'owner');
        $member = User::factory()->create();
        $this->assignRole($member, 'member');

        $rows = $this->indexBy($this->getJson('/api/people')->assertOk()->json('data'), 'id');

        $this->assertFalse($rows[$owner->id]['canRemove'], 'Nobody removes themselves from the people list.');
        $this->assertFalse($rows[$owner->id]['canAssignRole']);
        $this->assertTrue($rows[$member->id]['canRemove']);
        $this->assertTrue($rows[$member->id]['canAssignRole']);
    }

    #[Test]
    public function each_half_is_gated_on_its_own_ability(): void
    {
        $this->seedRoles();

        // A member sees neither half.
        $this->createAndActAs(role: 'member');
        $this->getJson('/api/people')->assertForbidden();

        // An admin sees both.
        $this->createAndActAs(role: 'admin');
        $this->invite('bob@example.test');
        $this->getJson('/api/people')->assertOk()->assertJsonPath('counts.pending', 1);
    }

    #[Test]
    public function a_viewer_without_users_view_gets_the_invitations_and_nothing_else(): void
    {
        $this->seedRoles();
        $member = Role::byKey('member');
        $member->abilities = ['app.access', 'invites.view'];
        $member->save();

        $this->createAndActAs(role: 'member');
        $this->invite('bob@example.test');

        $response = $this->getJson('/api/people')->assertOk();

        $this->assertSame(['invite'], array_column($response->json('data'), 'kind'));
        $this->assertSame(0, $response->json('counts.active'));
    }
}
