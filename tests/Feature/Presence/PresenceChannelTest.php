<?php

declare(strict_types=1);

namespace Tests\Feature\Presence;

use App\Data\PresenceMemberData;
use App\Models\User;
use App\Support\Presence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(Presence::class)]
#[CoversClass(PresenceMemberData::class)]
final class PresenceChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The null broadcaster answers every auth request without consulting
        // a channel callback, so the gate under test would never run.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);

        // Channels are registered on whichever driver was default when the
        // broadcasting routes booted, which was the null one. Re-run the file
        // so the callbacks land on the driver the assertions go through.
        require base_path('routes/channels.php');
    }

    #[Test]
    public function the_resource_segment_names_the_ability_that_opens_the_channel(): void
    {
        $this->assertSame('users.view', Presence::ability('users'));
        $this->assertSame('app.access', Presence::ability('whatever'), 'an unknown resource falls back to the app gate');
    }

    #[Test]
    public function a_member_of_the_channel_gets_a_roster_payload(): void
    {
        $user = $this->createAndActAs(role: 'admin');

        $response = $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-presence.users',
        ])->assertOk();

        $data = json_decode((string) $response->json('channel_data'), true);

        $this->assertSame($user->id, $data['user_id']);
        $this->assertSame($user->name, $data['user_info']['name']);
        $this->assertSame(PresenceMemberData::color($user->id), $data['user_info']['color']);
        $this->assertArrayNotHasKey('email', $data['user_info'], 'the roster is not a directory');
    }

    #[Test]
    public function the_ability_is_what_keeps_someone_off_the_channel(): void
    {
        $this->createAndActAs(role: 'member');

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-presence.users',
        ])->assertForbidden();
    }

    #[Test]
    public function a_resource_segment_the_pattern_rejects_never_reaches_the_gate(): void
    {
        $user = $this->createAndActAs(role: 'owner');

        $this->assertNull(Presence::join($user, 'Users'));
        $this->assertNull(Presence::join($user, '../secrets'));
    }

    #[Test]
    public function a_signed_out_visitor_is_not_on_any_roster(): void
    {
        User::factory()->create();

        // 403, not 401: the broadcaster denies a channel it has no user for
        // rather than inviting the visitor to sign in.
        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'presence-presence.users',
        ])->assertForbidden();
    }
}
