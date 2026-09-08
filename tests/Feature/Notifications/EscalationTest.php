<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Notification;
use App\Models\User;
use App\Notifications\AppNotification;
use App\Notifications\Channels\InboxChannel;
use App\Notifications\SecurityAlertNotification;
use App\Support\Notifications\ChannelResolver;
use App\Support\Notifications\NotificationRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AppNotification::class)]
#[CoversClass(ChannelResolver::class)]
#[CoversClass(InboxChannel::class)]
final class EscalationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'features.push' => true,
            'features.sms' => true,
            'notifications.escalation_minutes' => 5,
        ]);
    }

    #[Test]
    public function the_ladder_skips_a_channel_the_account_cannot_be_reached_on(): void
    {
        $user = User::factory()->create();
        $notification = new SecurityAlertNotification('password_changed', '203.0.113.9', null);

        // No browser signed up, no verified phone: mail is the only rung
        // left, and the inbox row is always written.
        $this->assertSame(['database', 'mail'], $notification->via($user));

        $this->subscribeBrowser($user);

        // SMS is never on by default, even for a type that offers it: it has
        // to be asked for, and the number has to have answered.
        $user->notificationPreferences()->create([
            'type' => 'security.alert',
            'channels' => ['push', 'mail', 'sms'],
        ]);
        $user->phone = '+15551234567';
        $user->phone_verified_at = now();
        $user->save();

        app(ChannelResolver::class)->forget($user);

        // Declared order, not the order the preference listed them in.
        $this->assertSame(['database', 'push', 'mail', 'sms'], $notification->via($user->fresh()));
    }

    #[Test]
    public function a_required_channel_survives_switching_everything_off(): void
    {
        $user = User::factory()->create();
        $user->notificationPreferences()->create(['type' => 'security.alert', 'channels' => []]);

        $notification = new SecurityAlertNotification('password_changed', null, null);

        $this->assertSame(['database', 'mail'], $notification->via($user));
    }

    #[Test]
    public function only_the_first_rung_goes_out_now(): void
    {
        $user = User::factory()->create();
        $this->subscribeBrowser($user);

        $notification = new SecurityAlertNotification('password_changed', null, null);
        $fresh = $user->fresh();

        $this->assertNull($notification->withDelay($fresh, 'database'));
        $this->assertNull($notification->withDelay($fresh, 'push'));
        $this->assertSame(5, (int) round(now()->diffInMinutes($notification->withDelay($fresh, 'mail'))));
    }

    #[Test]
    public function a_later_rung_stops_once_the_notification_has_been_seen(): void
    {
        $user = User::factory()->create();
        $this->subscribeBrowser($user);

        $notification = new SecurityAlertNotification('password_changed', null, null);
        $notification->id = (string) Str::uuid();

        $row = Notification::factory()->create([
            'id' => $notification->id,
            'notifiable_id' => $user->id,
        ]);

        $fresh = $user->fresh();

        $this->assertTrue($notification->shouldSend($fresh, 'push'));
        $this->assertTrue($notification->shouldSend($fresh, 'mail'));

        $row->markAsRead();

        // The push landed and the person looked. The queued mail behind it
        // now declines to send.
        $this->assertFalse($notification->shouldSend($fresh, 'mail'));
    }

    #[Test]
    public function the_inbox_row_records_the_plan_and_every_delivery(): void
    {
        $user = User::factory()->create();
        $this->subscribeBrowser($user);

        $notification = new SecurityAlertNotification('password_changed', '203.0.113.9', null);
        $notification->id = (string) Str::uuid();

        app(InboxChannel::class)->send($user->fresh(), $notification);

        $row = Notification::query()->firstOrFail();

        $this->assertSame('security.alert', $row->type);
        // The plan, written at insert time rather than patched in afterwards.
        $this->assertSame(['push', 'mail'], $row->channels);
        $this->assertArrayHasKey('database', $row->deliveries);
        $this->assertSame(
            ['event' => 'password_changed', 'ipAddress' => '203.0.113.9', 'device' => __('auth.unknown_device')],
            $row->data,
        );

        // What actually went out is stamped by the listener as each rung lands.
        Event::dispatch(new NotificationSent($user, $notification, 'mail', null));

        $this->assertArrayHasKey('mail', $row->fresh()->deliveries);
    }

    #[Test]
    public function someone_with_no_account_still_gets_the_mail(): void
    {
        $notification = new SecurityAlertNotification('password_changed', null, null);

        $this->assertSame(['mail'], $notification->via(new AnonymousNotifiable));
    }

    #[Test]
    public function every_shipped_notification_type_is_discovered_once(): void
    {
        $types = app(NotificationRegistry::class)->all();

        $this->assertArrayHasKey('security.alert', $types);
        $this->assertArrayHasKey('people.invite_accepted', $types);
        // Transactional mail carries no attribute and must stay out of reach
        // of a preferences screen.
        $this->assertArrayNotHasKey('', $types);
        $this->assertSame(SecurityAlertNotification::class, $types['security.alert']->class);
    }

    private function subscribeBrowser(User $user): void
    {
        $user->updatePushSubscription('https://push.example/'.$user->id, 'key', 'token', 'aesgcm');
    }
}
