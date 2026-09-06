<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Actions\Invites\ResendInvite;
use App\Http\Controllers\Account\InviteController;
use App\Models\Invite;
use App\Models\Role;
use App\Notifications\InviteNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(InviteController::class)]
#[CoversClass(ResendInvite::class)]
final class InviteResendTest extends TestCase
{
    use RefreshDatabase;

    private function invite(): Invite
    {
        $this->seedRoles();

        return Invite::query()->create([
            'email' => 'guest@example.test',
            'role_id' => Role::byKey('member')->id,
            'token_hash' => Invite::hashToken('the-original-token'),
            'expires_at' => now()->subDay(),
        ]);
    }

    #[Test]
    public function resending_mails_a_new_token_and_refreshes_the_expiry(): void
    {
        Notification::fake();
        $this->createAndActAs(role: 'admin');
        $invite = $this->invite();

        $this->postJson("/api/invites/{$invite->id}/resend")
            ->assertOk()
            ->assertJsonPath('status', 'pending');

        $fresh = $invite->fresh();
        $this->assertTrue($fresh?->expires_at->isFuture());
        $this->assertFalse($fresh->tokenMatches('the-original-token'), 'The old token must stop working.');

        Notification::assertSentTo(
            new AnonymousNotifiable,
            InviteNotification::class,
            fn (InviteNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'guest@example.test',
        );
    }

    #[Test]
    public function resending_takes_the_invites_manage_ability(): void
    {
        Notification::fake();
        $this->createAndActAs(role: 'member');
        $invite = $this->invite();

        $this->postJson("/api/invites/{$invite->id}/resend")->assertForbidden();

        $this->assertTrue($invite->fresh()?->tokenMatches('the-original-token'));
        Notification::assertNothingSent();
    }

    #[Test]
    public function an_invitation_that_was_already_answered_is_not_resent(): void
    {
        Notification::fake();
        $this->createAndActAs(role: 'admin');
        $invite = $this->invite();
        $invite->accepted_at = now();
        $invite->save();

        $this->postJson("/api/invites/{$invite->id}/resend")->assertStatus(422);

        Notification::assertNothingSent();
    }
}
