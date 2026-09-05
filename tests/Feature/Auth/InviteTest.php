<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Invites\AcceptInvite;
use App\Actions\Invites\CreateInvite;
use App\Actions\Invites\DeclineInvite;
use App\Http\Controllers\Account\InviteController;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use App\Notifications\InviteNotification;
use App\Policies\InvitePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(InviteController::class)]
#[CoversClass(CreateInvite::class)]
#[CoversClass(AcceptInvite::class)]
#[CoversClass(DeclineInvite::class)]
#[CoversClass(InvitePolicy::class)]
final class InviteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{Invite, string}
     */
    private function invite(string $email = 'guest@example.test', string $role = 'member'): array
    {
        Notification::fake();
        $this->seedRoles();
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $invite = app(CreateInvite::class)->execute($email, Role::byKey($role), $admin)->id;

        $token = null;
        Notification::assertSentTo(new AnonymousNotifiable, InviteNotification::class, function (InviteNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$token, $email) {
            $url = $notification->toMail($notifiable)->actionUrl;
            $token = substr($url, strrpos($url, 'token=') + 6);

            return $notifiable->routes['mail'] === $email;
        });

        return [Invite::query()->findOrFail($invite), (string) $token];
    }

    #[Test]
    public function members_cannot_manage_invites(): void
    {
        $this->createAndActAs(role: 'member');

        $this->getJson('/api/invites')->assertForbidden();
        $this->postJson('/api/invites', ['email' => 'x@example.test', 'role' => 'member'])->assertForbidden();
    }

    #[Test]
    public function admins_list_and_revoke_invites(): void
    {
        [$invite] = $this->invite();
        $admin = User::factory()->create();
        $this->assignRole($admin, 'admin');

        $this->actingAs($admin)->postJson('/api/invites', ['email' => 'second@example.test', 'role' => 'member'])
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('role', 'member');

        $this->getJson('/api/invites')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'second@example.test')
            ->assertJsonPath('data.1.email', 'guest@example.test')
            ->assertJsonMissingPath('data.0.token_hash');

        $this->deleteJson("/api/invites/{$invite->id}")->assertNoContent();
        $this->assertModelMissing($invite);
    }

    #[Test]
    public function the_public_page_is_gated_by_the_token(): void
    {
        [$invite, $token] = $this->invite();

        $this->getJson("/api/invites/{$invite->id}?token=".str_repeat('x', 48))->assertNotFound();
        $this->getJson("/api/invites/{$invite->id}?token={$token}")
            ->assertOk()
            ->assertJsonPath('email', 'guest@example.test')
            ->assertJsonPath('accountExists', false);
    }

    #[Test]
    public function an_anonymous_visitor_accepts_by_creating_an_account(): void
    {
        [$invite, $token] = $this->invite(role: 'admin');

        $this->postJson("/api/invites/{$invite->id}/accept", [
            'token' => $token,
            'name' => 'Guest',
            'password' => 'guest-long-password',
            'password_confirmation' => 'guest-long-password',
        ])->assertOk()->assertJsonPath('user.role', 'admin')->assertJsonPath('user.emailVerified', true);

        $user = User::query()->where('email', 'guest@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($invite->refresh()->accepted_at);

        $this->post('/auth/logout');
        $this->postJson("/api/invites/{$invite->id}/accept", ['token' => $token, 'name' => 'Again', 'password' => 'guest-long-password', 'password_confirmation' => 'guest-long-password'])
            ->assertForbidden();
    }

    #[Test]
    public function a_signed_in_user_with_the_invited_address_accepts_in_place(): void
    {
        [$invite, $token] = $this->invite();
        $user = User::factory()->create(['email' => 'guest@example.test']);

        $this->actingAs($user)->postJson("/api/invites/{$invite->id}/accept", ['token' => $token])
            ->assertOk()
            ->assertJsonPath('user.role', 'member');

        $stranger = User::factory()->create();
        [$second, $secondToken] = $this->invite('someone-else@example.test');
        $this->actingAs($stranger)->postJson("/api/invites/{$second->id}/accept", ['token' => $secondToken])->assertForbidden();
    }

    #[Test]
    public function declined_and_expired_invites_cannot_be_used(): void
    {
        [$invite, $token] = $this->invite();

        $this->postJson("/api/invites/{$invite->id}/decline", ['token' => $token])->assertNoContent();
        $this->getJson("/api/invites/{$invite->id}?token={$token}")->assertNotFound();

        [$expired, $expiredToken] = $this->invite('late@example.test');
        $this->travel(8)->days();

        $this->getJson("/api/invites/{$expired->id}?token={$expiredToken}")->assertNotFound();
        $this->postJson("/api/invites/{$expired->id}/accept", ['token' => $expiredToken, 'name' => 'L', 'password' => 'late-long-password', 'password_confirmation' => 'late-long-password'])
            ->assertForbidden();
    }
}
