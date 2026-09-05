<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\ImpersonationController;
use App\Http\Middleware\PreventDuringImpersonation;
use App\Models\User;
use App\Services\Auth\ImpersonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ImpersonationController::class)]
#[CoversClass(ImpersonationService::class)]
#[CoversClass(PreventDuringImpersonation::class)]
final class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function root_can_impersonate_and_hand_back_but_cannot_touch_second_factors(): void
    {
        $this->seedRoles();
        $root = User::factory()->root()->create();
        $target = User::factory()->create();
        $this->assignRole($target, 'member');

        $this->actingAs($root);

        $this->postJson('/auth/impersonate', ['userId' => $target->id])
            ->assertOk()
            ->assertJsonPath('user.id', $target->id)
            ->assertJsonPath('impersonating', true);

        $this->getJson('/auth/me')->assertJsonPath('user.id', $target->id);

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();
        $this->postJson('/auth/2fa/setup')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'IMPERSONATING');

        $this->deleteJson('/auth/impersonate')->assertOk()->assertJsonPath('user.id', $root->id);
        $this->deleteJson('/auth/impersonate')->assertStatus(409);
    }

    #[Test]
    public function owners_may_not_impersonate(): void
    {
        $this->seedRoles();
        $this->createAndActAs(role: 'owner');
        $target = User::factory()->create();

        $this->postJson('/auth/impersonate', ['userId' => $target->id])->assertForbidden();
    }

    #[Test]
    public function root_may_not_impersonate_another_root(): void
    {
        $root = User::factory()->root()->create();
        $otherRoot = User::factory()->root()->create();

        $this->actingAs($root)->postJson('/auth/impersonate', ['userId' => $otherRoot->id])->assertForbidden();
    }
}
