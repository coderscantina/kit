<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Role;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AuthorizationService::class)]
final class AuthorizationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_answers_from_the_users_role(): void
    {
        $this->seedRoles();
        $member = User::factory()->create(['role_id' => Role::byKey('member')->id]);
        $admin = User::factory()->create(['role_id' => Role::byKey('admin')->id]);
        $service = app(AuthorizationService::class);

        $this->assertTrue($service->can($member, 'app.access'));
        $this->assertFalse($service->can($member, 'users.manage'));
        $this->assertTrue($service->can($admin, 'users.manage'));
        $this->assertFalse($service->can($admin, 'roles.manage'));
    }

    #[Test]
    public function a_user_without_a_role_has_nothing_and_root_has_everything(): void
    {
        $service = app(AuthorizationService::class);

        $this->assertFalse($service->can(User::factory()->create(), 'app.access'));
        $this->assertTrue($service->can(User::factory()->root()->create(), 'anything.at_all'));
    }

    #[Test]
    public function a_role_change_is_visible_after_invalidation(): void
    {
        $this->seedRoles();
        $user = User::factory()->create(['role_id' => Role::byKey('member')->id]);
        $service = app(AuthorizationService::class);

        $this->assertFalse($service->can($user, 'users.view'));

        $user->role_id = Role::byKey('admin')->id;
        $user->save();

        $this->assertFalse($service->can($user, 'users.view'), 'cached graph still answers');

        $service->invalidateUser($user);

        $this->assertTrue($service->can($user, 'users.view'));
    }

    #[Test]
    public function sync_roles_drops_abilities_the_registry_does_not_know(): void
    {
        config(['abilities.roles.member.abilities' => ['app.access', 'made.up']]);

        $this->seedRoles();

        $this->assertSame(['app.access'], Role::byKey('member')->abilities);
    }
}
