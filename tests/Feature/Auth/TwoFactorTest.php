<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\PasswordConfirmController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Middleware\RequirePasswordVerification;
use App\Services\Auth\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(TwoFactorController::class)]
#[CoversClass(PasswordConfirmController::class)]
#[CoversClass(RequirePasswordVerification::class)]
#[CoversClass(TwoFactorAuthService::class)]
final class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function setup_needs_a_fresh_password_confirmation(): void
    {
        $this->createAndActAs(role: 'member');

        $this->postJson('/auth/2fa/setup')
            ->assertStatus(423)
            ->assertJsonPath('error_code', 'PASSWORD_CONFIRMATION_REQUIRED');

        $this->postJson('/auth/password/confirm', ['password' => 'wrong'])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'INVALID_PASSWORD');

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->postJson('/auth/2fa/setup')->assertOk()->assertJsonStructure(['secret', 'url']);
    }

    #[Test]
    public function the_confirmation_window_expires(): void
    {
        $this->createAndActAs(role: 'member');
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->travel(16)->minutes();

        $this->postJson('/auth/2fa/setup')->assertStatus(423);
    }

    #[Test]
    public function the_full_enable_disable_cycle(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $service = app(TwoFactorAuthService::class);
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $secret = $this->postJson('/auth/2fa/setup')->json('secret');

        $this->postJson('/auth/2fa/confirm', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('error_code', 'INVALID_TOTP_CODE');
        $this->assertFalse($user->refresh()->hasEnabledTwoFactor());

        $codes = $this->postJson('/auth/2fa/confirm', ['code' => $service->code($secret, time())])
            ->assertOk()
            ->json('backup_codes');

        $this->assertCount(8, $codes);
        $this->assertTrue($user->refresh()->hasEnabledTwoFactor());
        $this->assertSame($secret, $user->refresh()->two_factor_secret);

        $this->postJson('/auth/2fa/setup')->assertStatus(409);

        $regenerated = $this->postJson('/auth/2fa/backup-codes')->assertOk()->json('backup_codes');
        $this->assertNotSame($codes, $regenerated);
        $this->assertSame($regenerated, $user->refresh()->two_factor_backup_codes);

        $this->postJson('/auth/2fa/disable')->assertOk();
        $this->assertFalse($user->refresh()->hasEnabledTwoFactor());
        $this->assertNull($user->refresh()->two_factor_secret);
    }

    #[Test]
    public function totp_codes_follow_rfc_6238(): void
    {
        // RFC 6238 appendix B, SHA-1 test vector at T=59 for the ASCII secret 12345678901234567890.
        $service = app(TwoFactorAuthService::class);

        $this->assertSame('287082', $service->code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
        $this->assertSame('081804', $service->code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1111111109));
    }
}
