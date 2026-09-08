<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Account\ConfirmPhoneVerification;
use App\Actions\Account\RemovePhone;
use App\Actions\Account\RequestPhoneVerification;
use App\Http\Controllers\Account\PhoneController;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Notifications\PhoneVerificationNotification;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\RecordingSmsSender;
use Tests\TestCase;

#[CoversClass(PhoneController::class)]
#[CoversClass(RequestPhoneVerification::class)]
#[CoversClass(ConfirmPhoneVerification::class)]
#[CoversClass(RemovePhone::class)]
#[CoversClass(PhoneVerificationNotification::class)]
final class PhoneVerificationTest extends TestCase
{
    use RefreshDatabase;

    private RecordingSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sms = new RecordingSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
    }

    #[Test]
    public function a_code_goes_to_the_number_and_the_account_does_not_move_yet(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/phone', ['phone' => '+1 (555) 123-4567'])
            ->assertCreated()
            ->assertJsonPath('phone', null)
            ->assertJsonPath('verified', false)
            ->assertJsonPath('pendingPhone', '+15551234567');

        $this->assertNull($user->fresh()->phone);
        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('+15551234567', $this->sms->sent[0]->to);
        $this->assertMatchesRegularExpression('/\d{6}/', $this->sms->sent[0]->content);
    }

    #[Test]
    public function the_code_makes_the_number_a_route(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/phone', ['phone' => '+15551234567'])->assertCreated();

        $this->postJson('/api/account/phone/verify', ['code' => $this->sms->lastCode()])
            ->assertCreated()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('phone', '+15551234567');

        $fresh = $user->fresh();

        $this->assertTrue($fresh->hasVerifiedPhone());
        $this->assertSame('+15551234567', $fresh->routeNotificationForSms());
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => SecurityEvent::PHONE_VERIFIED]);
        $this->assertDatabaseCount('phone_verifications', 0);
    }

    #[Test]
    public function a_wrong_code_is_counted_and_the_row_dies_at_the_limit(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/phone', ['phone' => '+15551234567'])->assertCreated();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/account/phone/verify', ['code' => '000000'])
                ->assertJsonValidationErrors('code');
        }

        $this->assertSame(5, $user->fresh()->phoneVerification()->first()?->attempts);

        // Sixth guess: the row is spent, and the right code no longer helps.
        $this->postJson('/api/account/phone/verify', ['code' => $this->sms->lastCode()])
            ->assertJsonValidationErrors('code');

        $this->assertFalse($user->fresh()->hasVerifiedPhone());
    }

    #[Test]
    public function asking_again_straight_away_is_refused(): void
    {
        $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/phone', ['phone' => '+15551234567'])->assertCreated();
        $this->postJson('/api/account/phone', ['phone' => '+15551234567'])
            ->assertJsonValidationErrors('phone');

        $this->assertCount(1, $this->sms->sent);
    }

    #[Test]
    public function a_local_format_is_refused_because_nothing_can_guess_the_country(): void
    {
        $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/phone', ['phone' => '0555 123456'])
            ->assertJsonValidationErrors('phone');

        $this->assertSame([], $this->sms->sent);
    }

    #[Test]
    public function removing_the_number_takes_the_route_away(): void
    {
        $user = $this->createAndActAs(User::factory()->withVerifiedPhone()->create(), role: 'member');

        $this->deleteJson('/api/account/phone')
            ->assertOk()
            ->assertJsonPath('verified', false)
            ->assertJsonPath('phone', null);

        $this->assertNull($user->fresh()->routeNotificationForSms());
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => SecurityEvent::PHONE_REMOVED]);
    }

    #[Test]
    public function without_a_sender_there_is_no_phone_flow_at_all(): void
    {
        config(['features.sms' => false]);

        $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/phone', ['phone' => '+15551234567'])->assertNotFound();
    }
}
