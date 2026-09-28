<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\AccessTokenController;
use App\Models\SecurityEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AccessTokenController::class)]
final class AccessTokenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function minting_a_token_asks_for_the_password_and_shows_it_once(): void
    {
        $user = $this->createAndActAs(role: 'admin');

        $this->postJson('/api/account/tokens', ['name' => 'CI', 'abilities' => ['users.view']])->assertStatus(423);

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();
        $created = $this->postJson('/api/account/tokens', ['name' => 'CI', 'abilities' => ['users.view'], 'expires_in_days' => 30])
            ->assertCreated();

        $this->assertMatchesRegularExpression('/^\d+\|/', $created->json('plainTextToken'));
        $this->assertEqualsCanonicalizing(['app.access', 'users.view'], $created->json('token.abilities'));
        $this->assertNotNull($created->json('token.expiresAt'));

        $listed = $this->getJson('/api/account/tokens')->assertOk();
        $this->assertCount(1, $listed->json());
        $this->assertStringNotContainsString(explode('|', $created->json('plainTextToken'))[1], (string) $listed->getContent());

        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => SecurityEvent::TOKEN_CREATED]);
    }

    #[Test]
    public function a_token_cannot_carry_more_than_its_owner_may_do(): void
    {
        $this->createAndActAs(role: 'member');
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->postJson('/api/account/tokens', ['name' => 'Sneaky', 'abilities' => ['users.manage']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('abilities.0');
    }

    #[Test]
    public function a_token_is_refused_everywhere_but_the_mcp_endpoint(): void
    {
        $user = $this->createAndActAs(role: 'owner');
        $plain = $user->createToken('leaked', ['*'])->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->flushSession();

        $this->withToken($plain)->getJson('/api/account/sessions')->assertUnauthorized();
        $this->withToken($plain)->getJson('/api/account/tokens')->assertUnauthorized();
        $this->withToken($plain)->getJson('/auth/me')->assertUnauthorized();
    }

    #[Test]
    public function revoking_a_token_deletes_it(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $token = $user->createToken('old', ['app.access'])->accessToken;

        $this->deleteJson("/api/account/tokens/{$token->id}")->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => SecurityEvent::TOKEN_REVOKED]);
    }
}
