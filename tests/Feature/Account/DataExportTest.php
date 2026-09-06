<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\DataExportController;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DataExportController::class)]
final class DataExportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_export_needs_step_up_and_returns_the_account_as_a_download(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $this->getJson('/api/account/export')->assertStatus(423);

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $response = $this->getJson('/api/account/export')->assertOk();

        $this->assertStringContainsString('attachment; filename="account-export-', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame($user->email, $response->json('account.email'));
        $this->assertIsArray($response->json('sessions'));
        $this->assertIsArray($response->json('invitationsSent'));

        // The export is itself something the owner should see in the trail.
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => SecurityEvent::DATA_EXPORTED]);
    }

    #[Test]
    public function the_export_never_carries_a_secret(): void
    {
        $this->createAndActAs(role: 'member');
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $body = (string) $this->getJson('/api/account/export')->assertOk()->getContent();

        foreach (['password', 'two_factor_secret', 'token_hash', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    #[Test]
    public function an_operator_cannot_export_the_account_they_are_impersonating(): void
    {
        $root = $this->createAndActAs(User::factory()->root()->create(), role: 'owner');
        $target = User::factory()->create();
        $this->assignRole($target, 'member');

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();
        $this->postJson('/auth/impersonate', ['userId' => $target->id])->assertOk();

        $this->getJson('/api/account/export')->assertForbidden()->assertJsonPath('error_code', 'IMPERSONATING');
        $this->assertDatabaseMissing('security_events', ['user_id' => $target->id, 'event' => SecurityEvent::DATA_EXPORTED]);
    }
}
