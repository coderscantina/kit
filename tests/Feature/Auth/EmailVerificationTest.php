<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(EmailVerificationController::class)]
#[CoversClass(VerifyEmailNotification::class)]
final class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_signed_link_verifies_and_lands_on_the_login_page(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('auth.verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect('/login?verified=1');
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function a_tampered_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('auth.verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('other@example.test'),
        ]);

        $this->get($url)->assertForbidden();
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();
        $this->assertNull($user->refresh()->email_verified_at);
    }

    #[Test]
    public function resend_mails_the_notification_with_the_app_route(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->postJson('/auth/email/resend')->assertOk();

        Notification::assertSentTo($user, VerifyEmailNotification::class, function (VerifyEmailNotification $notification) use ($user) {
            return str_contains($notification->toMail($user)->actionUrl, '/auth/email/verify/'.$user->id.'/');
        });
    }
}
