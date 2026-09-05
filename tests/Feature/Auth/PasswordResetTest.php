<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\PasswordResetController;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PasswordResetController::class)]
final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_reset_link_points_at_the_spa_and_resets_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'mike@example.test']);

        $this->postJson('/auth/password/forgot', ['email' => 'mike@example.test'])->assertOk();
        $this->postJson('/auth/password/forgot', ['email' => 'nobody@example.test'])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token, $user) {
            $token = $notification->token;
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'http://localhost/reset-password?token=');
        });

        $this->postJson('/auth/password/reset', [
            'token' => $token,
            'email' => 'mike@example.test',
            'password' => 'new-long-password-1',
            'password_confirmation' => 'new-long-password-1',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-long-password-1', $user->refresh()->password ?? ''));
    }

    #[Test]
    public function a_bad_token_is_a_validation_error(): void
    {
        User::factory()->create(['email' => 'mike@example.test']);

        $this->postJson('/auth/password/reset', [
            'token' => 'wrong',
            'email' => 'mike@example.test',
            'password' => 'new-long-password-1',
            'password_confirmation' => 'new-long-password-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
