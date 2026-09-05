<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Users\DeleteUser;
use App\Http\Controllers\Account\ProfileController;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\Auth\MembershipGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(ProfileController::class)]
#[CoversClass(DeleteUser::class)]
#[CoversClass(MembershipGuard::class)]
final class ProfileTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function name_and_locale_update_without_step_up(): void
    {
        $this->createAndActAs(role: 'member');

        $this->patchJson('/api/account/profile', ['name' => 'Mike', 'locale' => 'de'])
            ->assertOk()
            ->assertJsonPath('name', 'Mike')
            ->assertJsonPath('locale', 'de');

        $this->patchJson('/api/account/profile', ['locale' => 'fr'])->assertUnprocessable();
    }

    #[Test]
    public function changing_the_email_needs_confirmation_and_restarts_verification(): void
    {
        Notification::fake();
        $user = $this->createAndActAs(role: 'member');

        $this->patchJson('/api/account/email', ['email' => 'new@example.test'])->assertStatus(423);

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->patchJson('/api/account/email', ['email' => 'new@example.test'])
            ->assertOk()
            ->assertJsonPath('emailVerified', false);

        Notification::assertSentTo($user, VerifyEmailNotification::class);
    }

    #[Test]
    public function changing_the_password_keeps_this_session_and_drops_the_others(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->putJson('/api/account/password', [
            'current_password' => 'password',
            'password' => 'new-long-password-1',
            'password_confirmation' => 'new-long-password-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-long-password-1', $user->refresh()->password ?? ''));
        $this->getJson('/auth/me')->assertOk();

        // A second browser still holds the old hash in its session.
        $this->session(['password_hash_web' => Hash::make('password')])->getJson('/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function an_account_can_be_deleted_unless_it_is_the_last_owner(): void
    {
        $this->seedRoles();
        $owner = $this->createAndActAs(role: 'owner');
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->deleteJson('/api/account')
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'LAST_OWNER');

        $other = User::factory()->create();
        $this->assignRole($other, 'owner');

        $this->deleteJson('/api/account')->assertNoContent();
        $this->assertModelMissing($owner);
        $this->assertGuest();
    }
}
