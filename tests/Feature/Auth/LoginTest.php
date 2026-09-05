<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Models\User;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(LoginController::class)]
#[CoversClass(LogoutController::class)]
#[CoversClass(MeController::class)]
final class LoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_verified_user_signs_in_and_gets_a_session(): void
    {
        $this->seedRoles();
        $user = User::factory()->create(['email' => 'mike@example.test']);
        $this->assignRole($user, 'member');

        $this->postJson('/auth/login', ['email' => 'mike@example.test', 'password' => 'password'])
            ->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);

        $this->getJson('/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'mike@example.test')
            ->assertJsonPath('user.role', 'member')
            ->assertJsonPath('impersonating', false)
            ->assertJsonPath('abilities', ['app.access']);
    }

    #[Test]
    public function wrong_credentials_are_a_401_without_detail(): void
    {
        User::factory()->create(['email' => 'mike@example.test']);

        $this->postJson('/auth/login', ['email' => 'mike@example.test', 'password' => 'nope'])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'These credentials do not match our records.');

        $this->assertGuest();
    }

    #[Test]
    public function an_unverified_user_is_told_to_verify_and_not_signed_in(): void
    {
        User::factory()->unverified()->create(['email' => 'new@example.test']);

        $this->postJson('/auth/login', ['email' => 'new@example.test', 'password' => 'password'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'EMAIL_NOT_VERIFIED');

        $this->assertGuest();
    }

    #[Test]
    public function two_factor_users_need_a_code_and_a_wrong_one_counts_against_them(): void
    {
        $service = app(TwoFactorAuthService::class);
        $secret = $service->generateSecret();
        $user = User::factory()->create([
            'email' => 'totp@example.test',
            'two_factor_secret' => $secret,
            'two_factor_backup_codes' => ['ABCDEFGHJK'],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/auth/login', ['email' => 'totp@example.test', 'password' => 'password'])
            ->assertStatus(423)
            ->assertJsonPath('error_code', 'TOTP_VERIFICATION_REQUIRED');
        $this->assertGuest();

        $this->postJson('/auth/login', ['email' => 'totp@example.test', 'password' => 'password'], ['X-TOTP-Code' => '000000'])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'INVALID_TOTP_CODE');
        $this->assertGuest();

        $this->postJson('/auth/login', ['email' => 'totp@example.test', 'password' => 'password'], ['X-TOTP-Code' => $service->code($secret, time())])
            ->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function a_backup_code_works_once(): void
    {
        $service = app(TwoFactorAuthService::class);
        $user = User::factory()->create([
            'email' => 'totp@example.test',
            'two_factor_secret' => $service->generateSecret(),
            'two_factor_backup_codes' => ['ABCDEFGHJK', 'ZZZZZZZZZZ'],
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/auth/login', ['email' => 'totp@example.test', 'password' => 'password'], ['X-TOTP-Code' => 'abcde-fghjk'])
            ->assertOk();

        $this->assertSame(['ZZZZZZZZZZ'], $user->refresh()->two_factor_backup_codes);

        $this->post('/auth/logout')->assertNoContent();

        $this->postJson('/auth/login', ['email' => 'totp@example.test', 'password' => 'password'], ['X-TOTP-Code' => 'ABCDEFGHJK'])
            ->assertForbidden();
    }

    #[Test]
    public function five_bad_codes_lock_the_second_factor_out(): void
    {
        $service = app(TwoFactorAuthService::class);
        $secret = $service->generateSecret();
        User::factory()->create([
            'email' => 'totp@example.test',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/auth/login', ['email' => 'totp@example.test', 'password' => 'password'], ['X-TOTP-Code' => '000000'])
                ->assertForbidden();
        }

        $this->postJson('/auth/login', ['email' => 'totp@example.test', 'password' => 'password'], ['X-TOTP-Code' => $service->code($secret, time())])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'TOO_MANY_ATTEMPTS');
    }

    #[Test]
    public function logout_ends_the_session(): void
    {
        $this->createAndActAs(role: 'member');

        $this->post('/auth/logout')->assertNoContent();

        $this->getJson('/auth/me')->assertUnauthorized();
    }
}
