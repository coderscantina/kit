<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\SecurityAlertNotification;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsMessage;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\RecordingSmsSender;
use Tests\TestCase;

#[CoversClass(SmsChannel::class)]
#[CoversClass(LogSmsSender::class)]
final class SmsChannelTest extends TestCase
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
    public function an_unverified_number_is_not_a_destination(): void
    {
        $user = User::factory()->create(['phone' => '+15551234567']);

        $this->channel()->send($user, new SecurityAlertNotification('password_changed', null, null));

        $this->assertSame([], $this->sms->sent);
    }

    #[Test]
    public function a_verified_number_gets_the_message(): void
    {
        $user = User::factory()->withVerifiedPhone()->create();

        $this->channel()->send($user, new SecurityAlertNotification('password_changed', null, null));

        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('+15551234567', $this->sms->sent[0]->to);
    }

    #[Test]
    public function nothing_is_sent_when_the_installation_has_no_sender(): void
    {
        config(['features.sms' => false]);

        $user = User::factory()->withVerifiedPhone()->create();

        $this->channel()->send($user, new SecurityAlertNotification('password_changed', null, null));

        $this->assertSame([], $this->sms->sent);
    }

    #[Test]
    public function the_log_sender_writes_the_message_instead_of_sending_it(): void
    {
        Log::shouldReceive('channel')->once()->andReturnSelf();
        Log::shouldReceive('info')->once()->with('SMS', [
            'to' => '+15551234567',
            'content' => 'hello',
        ]);

        (new LogSmsSender)->send(new SmsMessage('+15551234567', 'hello'));
    }

    private function channel(): SmsChannel
    {
        return $this->app->make(SmsChannel::class);
    }
}
