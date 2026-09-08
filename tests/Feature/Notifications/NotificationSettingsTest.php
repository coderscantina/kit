<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Http\Controllers\Account\NotificationSettingsController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(NotificationSettingsController::class)]
#[CoversClass(UpdateNotificationPreferences::class)]
final class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_matrix_is_built_from_the_notification_classes_the_app_ships(): void
    {
        $this->createAndActAs(role: 'member');

        $response = $this->getJson('/api/account/notifications')->assertOk();

        $keys = array_column($response->json('types'), 'key');

        $this->assertContains('security.alert', $keys);
        $this->assertContains('people.invite_accepted', $keys);

        $alert = $this->typeRow($response->json('types'), 'security.alert');

        $this->assertSame(['push', 'mail', 'sms'], $alert['channels']);
        $this->assertSame(['mail'], $alert['required']);
        // Never chosen, so the type's own defaults are what the screen shows.
        $this->assertSame(['push', 'mail'], $alert['enabled']);
    }

    #[Test]
    public function saving_an_empty_selection_is_a_choice_and_not_a_reset(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->putJson('/api/account/notifications', [
            'preferences' => ['people.invite_accepted' => []],
        ])->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'people.invite_accepted',
        ]);

        $invite = $this->typeRow($this->getJson('/api/account/notifications')->json('types'), 'people.invite_accepted');

        $this->assertSame([], $invite['enabled']);
    }

    #[Test]
    public function a_required_channel_is_written_back_even_when_it_was_not_sent(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->putJson('/api/account/notifications', [
            'preferences' => ['security.alert' => ['push']],
        ])->assertOk();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'security.alert',
            'channels' => json_encode(['push', 'mail']),
        ]);
    }

    #[Test]
    public function a_channel_the_type_does_not_offer_is_dropped_rather_than_rejected(): void
    {
        $this->createAndActAs(role: 'member');

        $this->putJson('/api/account/notifications', [
            'preferences' => ['people.invite_accepted' => ['push', 'sms']],
        ])->assertOk();

        $invite = $this->typeRow($this->getJson('/api/account/notifications')->json('types'), 'people.invite_accepted');

        $this->assertSame(['push'], $invite['enabled']);
    }

    #[Test]
    public function an_unknown_channel_is_a_validation_error(): void
    {
        $this->createAndActAs(role: 'member');

        $this->putJson('/api/account/notifications', [
            'preferences' => ['security.alert' => ['carrier-pigeon']],
        ])->assertJsonValidationErrors('preferences.security.alert.0');
    }

    #[Test]
    public function a_signed_out_visitor_gets_nothing(): void
    {
        $this->getJson('/api/account/notifications')->assertUnauthorized();
    }

    /**
     * @param  array<int, array<string, mixed>>  $types
     * @return array<string, mixed>
     */
    private function typeRow(array $types, string $key): array
    {
        foreach ($types as $type) {
            if ($type['key'] === $key) {
                return $type;
            }
        }

        $this->fail("The settings payload has no type '{$key}'.");
    }
}
