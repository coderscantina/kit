<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\SecurityActivityController;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SecurityActivityController::class)]
#[CoversClass(SecurityLog::class)]
final class SecurityActivityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function signing_in_and_changing_a_password_both_land_in_the_trail(): void
    {
        $user = User::factory()->create();
        $this->assignRole($user, 'member');

        $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();
        $this->putJson('/api/account/password', [
            'current_password' => 'password',
            'password' => 'a-much-longer-secret',
            'password_confirmation' => 'a-much-longer-secret',
        ])->assertOk();

        $events = array_column($this->getJson('/api/account/security-activity')->assertOk()->json('data'), 'event');

        $this->assertSame([SecurityEvent::PASSWORD_CHANGED, SecurityEvent::SIGNED_IN], $events);
    }

    #[Test]
    public function the_trail_carries_the_device_and_never_another_account(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $stranger = User::factory()->create();

        app(SecurityLog::class)->record($user, SecurityEvent::TWO_FACTOR_ENABLED);
        app(SecurityLog::class)->record($stranger, SecurityEvent::TWO_FACTOR_DISABLED);

        $rows = $this->getJson('/api/account/security-activity')->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame(SecurityEvent::TWO_FACTOR_ENABLED, $rows[0]['event']);
        $this->assertArrayHasKey('device', $rows[0]);
    }
}
