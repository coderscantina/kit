<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Actions\Account\ConfirmEmailChange;
use App\Actions\Account\RequestEmailChange;
use App\Http\Controllers\Account\EmailChangeController;
use App\Models\EmailChange;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\ConfirmEmailChangeNotification;
use App\Notifications\EmailChangedNotification;
use App\Notifications\EmailChangeRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(EmailChangeController::class)]
#[CoversClass(RequestEmailChange::class)]
#[CoversClass(ConfirmEmailChange::class)]
final class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requests a change for the acting user and digs the one-time token out of
     * the mail sent to the new address.
     */
    private function request(string $email = 'new@example.test'): string
    {
        Notification::fake();

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();
        $this->postJson('/api/account/email', ['email' => $email])->assertOk();

        $token = null;

        Notification::assertSentTo(
            new AnonymousNotifiable,
            ConfirmEmailChangeNotification::class,
            function (ConfirmEmailChangeNotification $notification, array $channels, AnonymousNotifiable $notifiable) use (&$token, $email): bool {
                $url = $notification->toMail($notifiable)->actionUrl;
                $token = substr((string) $url, strrpos((string) $url, 'token=') + 6);

                return $notifiable->routes['mail'] === $email;
            },
        );

        return (string) $token;
    }

    #[Test]
    public function requesting_a_change_needs_step_up_and_leaves_the_address_alone(): void
    {
        Notification::fake();
        $user = $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/email', ['email' => 'new@example.test'])->assertStatus(423);

        $token = $this->request();

        $this->assertNotSame('', $token);
        $this->assertSame($user->email, $user->fresh()?->email, 'The address must not move before confirmation.');
        $this->assertDatabaseHas('email_changes', ['user_id' => $user->id, 'new_email' => 'new@example.test']);

        // The old address is told while it can still do something about it.
        Notification::assertSentTo($user, EmailChangeRequestedNotification::class);
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => SecurityEvent::EMAIL_CHANGE_REQUESTED]);
    }

    #[Test]
    public function the_pending_address_is_reported_to_the_client(): void
    {
        $this->createAndActAs(role: 'member');
        $this->request();

        $this->getJson('/auth/me')->assertOk()->assertJsonPath('user.pendingEmail', 'new@example.test');
    }

    #[Test]
    public function confirming_with_the_token_moves_the_address_and_marks_it_verified(): void
    {
        $user = $this->createAndActAs(User::factory()->unverified()->create(), role: 'member');
        $previous = $user->email;
        $token = $this->request();
        $change = EmailChange::query()->where('user_id', $user->id)->firstOrFail();

        $this->postJson("/api/account/email/confirm/{$change->id}", ['token' => $token])->assertNoContent();

        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('new@example.test', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at, 'Opening the link proves the inbox, so the address arrives verified.');
        $this->assertDatabaseEmpty('email_changes');

        // The receipt goes to the address that just lost the account.
        Notification::assertSentTo(
            new AnonymousNotifiable,
            EmailChangedNotification::class,
            fn (EmailChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $previous,
        );
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => SecurityEvent::EMAIL_CHANGED]);
    }

    #[Test]
    public function a_wrong_or_expired_token_confirms_nothing(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $this->request();
        $change = EmailChange::query()->where('user_id', $user->id)->firstOrFail();

        $this->postJson("/api/account/email/confirm/{$change->id}", ['token' => 'not-the-token'])->assertNotFound();

        $change->expires_at = now()->subMinute();
        $change->save();

        $this->postJson("/api/account/email/confirm/{$change->id}", ['token' => 'anything'])->assertNotFound();
        $this->assertSame($user->email, $user->fresh()?->email);
    }

    #[Test]
    public function an_address_claimed_in_the_meantime_is_refused(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $token = $this->request();
        $change = EmailChange::query()->where('user_id', $user->id)->firstOrFail();

        User::factory()->create(['email' => 'new@example.test']);

        $this->postJson("/api/account/email/confirm/{$change->id}", ['token' => $token])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');

        $this->assertSame($user->email, $user->fresh()?->email);
    }

    #[Test]
    public function a_pending_change_can_be_cancelled_without_step_up(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $this->request();

        $this->deleteJson('/api/account/email')->assertOk()->assertJsonPath('pendingEmail', null);
        $this->assertDatabaseEmpty('email_changes');
        $this->assertSame($user->email, $user->fresh()?->email);
    }
}
