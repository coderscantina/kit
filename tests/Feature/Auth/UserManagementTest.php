<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Users\ChangeUserRole;
use App\Http\Controllers\Account\UserController;
use App\Http\Middleware\RequireTotpVerification;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(UserController::class)]
#[CoversClass(UserPolicy::class)]
#[CoversClass(ChangeUserRole::class)]
#[CoversClass(RequireTotpVerification::class)]
final class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admins_list_users_but_only_owners_change_roles(): void
    {
        $this->seedRoles();
        $member = User::factory()->create();
        $this->assignRole($member, 'member');
        $admin = $this->createAndActAs(role: 'admin');
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->getJson('/api/users?per_page=1000')
            ->assertOk()
            ->assertJsonPath('per_page', 100)
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/roles')->assertOk()->assertJsonPath('0.key', 'owner');

        $this->patchJson("/api/users/{$member->id}/role", ['role' => 'admin'])->assertForbidden();
        $this->assertRole($member, 'member');
        $this->assertTrue($admin->hasRole('admin'));
    }

    #[Test]
    public function the_user_list_filters_by_search_and_sorts_only_by_allowed_columns(): void
    {
        $this->seedRoles();
        $this->createAndActAs(
            User::factory()->create(['name' => 'Zoe Zander', 'email' => 'zoe@example.test']),
            role: 'admin'
        );
        $this->assignRole(User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.test']), 'member');

        $this->getJson('/api/users?search=lovelace')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ada Lovelace');

        $this->getJson('/api/users?search=@example.test')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/users?sort=name&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Zoe Zander');

        // Not on the allow list, so it falls back to the default rather than
        // reaching orderBy() with whatever the client sent.
        $this->getJson('/api/users?sort=password')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Ada Lovelace');
    }

    #[Test]
    public function role_changes_are_step_up_protected_and_respect_the_last_owner(): void
    {
        $this->seedRoles();
        $owner = $this->createAndActAs(role: 'owner');
        $member = User::factory()->create();
        $this->assignRole($member, 'member');

        $this->patchJson("/api/users/{$member->id}/role", ['role' => 'admin'])
            ->assertStatus(423)
            ->assertJsonPath('error_code', 'PASSWORD_CONFIRMATION_REQUIRED');

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->patchJson("/api/users/{$member->id}/role", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('role', 'admin');

        $this->patchJson("/api/users/{$owner->id}/role", ['role' => 'member'])->assertForbidden();

        $secondOwner = User::factory()->create();
        $this->assignRole($secondOwner, 'owner');
        $this->assignRole($owner, 'admin');
        $this->assignRole($owner, 'owner');

        $this->patchJson("/api/users/{$secondOwner->id}/role", ['role' => 'member'])->assertOk();
        $this->assertRole($secondOwner, 'member');

        $this->assignRole($secondOwner, 'owner');
        $this->assignRole($owner, 'admin');
        $this->assignRole($owner, 'owner');
        $this->assignRole($secondOwner, 'member');

        $this->deleteJson("/api/users/{$owner->id}")->assertForbidden();
    }

    #[Test]
    public function the_last_owner_cannot_be_demoted_or_deleted(): void
    {
        $this->seedRoles();
        $root = User::factory()->root()->create();
        $owner = User::factory()->create();
        $this->assignRole($owner, 'owner');

        $this->actingAs($root);
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->patchJson("/api/users/{$owner->id}/role", ['role' => 'member'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'LAST_OWNER');

        $this->deleteJson("/api/users/{$owner->id}")->assertStatus(409);
        $this->assertModelExists($owner);
    }
}
