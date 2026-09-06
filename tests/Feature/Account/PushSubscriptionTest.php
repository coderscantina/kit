<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\PushSubscriptionController;
use App\Notifications\TestPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(PushSubscriptionController::class)]
final class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function subscription(string $endpoint = 'https://push.example.test/abc'): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => ['p256dh' => 'public-key', 'auth' => 'auth-token'],
            'contentEncoding' => 'aes128gcm',
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['webpush.vapid.public_key' => 'test-public-key']);
    }

    #[Test]
    public function without_a_vapid_key_there_is_no_push_feature_to_subscribe_to(): void
    {
        config(['webpush.vapid.public_key' => null]);
        $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/push-subscriptions', $this->subscription())->assertNotFound();
        $this->postJson('/api/account/push-subscriptions/test')->assertNotFound();
    }

    #[Test]
    public function a_browser_subscribes_once_however_often_it_asks(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/push-subscriptions', $this->subscription())->assertCreated();
        $this->postJson('/api/account/push-subscriptions', $this->subscription())->assertCreated();

        $this->assertSame(1, $user->pushSubscriptions()->count());
    }

    #[Test]
    public function unsubscribing_without_an_endpoint_drops_every_device(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/push-subscriptions', $this->subscription())->assertCreated();
        $this->postJson('/api/account/push-subscriptions', $this->subscription('https://push.example.test/def'))
            ->assertCreated();

        $this->deleteJson('/api/account/push-subscriptions', ['endpoint' => 'https://push.example.test/abc'])
            ->assertNoContent();
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->deleteJson('/api/account/push-subscriptions')->assertNoContent();
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    #[Test]
    public function the_test_notification_needs_a_device_to_land_on(): void
    {
        Notification::fake();
        $user = $this->createAndActAs(role: 'member');

        $this->postJson('/api/account/push-subscriptions/test')->assertStatus(422);

        $this->postJson('/api/account/push-subscriptions', $this->subscription())->assertCreated();
        $this->postJson('/api/account/push-subscriptions/test')->assertOk();

        Notification::assertSentTo($user, TestPushNotification::class);
    }
}
