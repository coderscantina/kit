<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\SocialLoginController;
use App\Models\User;
use App\Models\UserSocialLink;
use App\Support\SocialProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SocialLoginController::class)]
#[CoversClass(SocialProviders::class)]
final class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private SocialiteUser $socialUser;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'id',
            'services.google.client_secret' => 'secret',
        ]);

        // Socialite's driver is the network; the callback is what is under
        // test. One mock for the whole test, reading whatever the current
        // fakeCallback() put on $socialUser: a second shouldReceive() would
        // never fire, because the first expectation already matches.
        $provider = Mockery::mock(AbstractProvider::class);
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('scopes')->andReturnSelf();
        $provider->shouldReceive('user')->andReturnUsing(fn (): SocialiteUser => $this->socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    /**
     * What the provider will report on the next callback.
     *
     * @param  array<string, mixed>  $raw
     */
    private function fakeCallback(string $email, string $id = 'ext-1', array $raw = ['email_verified' => true]): void
    {
        $socialUser = (new SocialiteUser)->map([
            'id' => $id,
            'name' => 'Ada Lovelace',
            'nickname' => 'ada',
            'email' => $email,
        ]);
        $socialUser->setRaw($raw);

        $this->socialUser = $socialUser;
    }

    #[Test]
    public function an_unconfigured_provider_has_no_endpoint(): void
    {
        config(['services.google.client_id' => null]);

        $this->get('/auth/social/google/redirect')->assertNotFound();
        $this->get('/auth/social/gitlab/redirect')->assertNotFound();
    }

    #[Test]
    public function a_first_sign_in_creates_the_account_and_links_the_provider(): void
    {
        $this->seedRoles();
        $this->fakeCallback('ada@example.test');

        $this->get('/auth/social/google/callback')->assertRedirect('/');

        $user = User::query()->where('email', 'ada@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('user_social_links', [
            'user_id' => $user->id,
            'provider' => 'google',
            'external_id' => 'ext-1',
        ]);
    }

    #[Test]
    public function an_existing_account_is_only_adopted_when_both_sides_verified_the_address(): void
    {
        $this->seedRoles();
        $user = User::factory()->create(['email' => 'ada@example.test', 'email_verified_at' => null]);

        // Local address unverified: an attacker could have registered it.
        $this->fakeCallback('ada@example.test');
        $this->get('/auth/social/google/callback')->assertRedirect('/login?social_error=1&provider=google');
        $this->assertGuest();

        // Verified locally, but the provider does not vouch for it either.
        $user->email_verified_at = now();
        $user->save();
        $this->fakeCallback('ada@example.test', raw: ['email_verified' => false]);
        $this->get('/auth/social/google/callback')->assertRedirect('/login?social_error=1&provider=google');
        $this->assertGuest();

        // Both sides verified: the accounts are the same person.
        $this->fakeCallback('ada@example.test');
        $this->get('/auth/social/google/callback')->assertRedirect('/');
        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    public function a_second_factor_still_stands_between_the_provider_and_the_session(): void
    {
        $this->seedRoles();
        $user = User::factory()->create(['email' => 'ada@example.test', 'email_verified_at' => now()]);
        UserSocialLink::query()->create([
            'user_id' => $user->id, 'provider' => 'google', 'external_id' => 'ext-1',
        ]);
        $user->two_factor_secret = 'JBSWY3DPEHPK3PXP';
        $user->two_factor_confirmed_at = now();
        $user->two_factor_backup_codes = ['BACKUPCODE'];
        $user->save();

        $this->fakeCallback('ada@example.test');
        $this->get('/auth/social/google/callback')->assertRedirect('/login?social_2fa=1');
        $this->assertGuest();

        $this->postJson('/auth/social/2fa', ['code' => 'nope'])->assertStatus(403);
        $this->assertGuest();

        $this->postJson('/auth/social/2fa', ['code' => 'BACKUPCODE'])
            ->assertOk()
            ->assertJsonPath('redirect', '/');
        $this->assertAuthenticatedAs($user->fresh());
    }

    #[Test]
    public function linking_refuses_an_identity_that_already_belongs_to_someone_else(): void
    {
        $this->seedRoles();
        $other = User::factory()->create(['email' => 'other@example.test']);
        UserSocialLink::query()->create([
            'user_id' => $other->id, 'provider' => 'google', 'external_id' => 'ext-1',
        ]);

        $this->createAndActAs(User::factory()->create(['email' => 'ada@example.test']), role: 'member');
        $this->fakeCallback('ada@example.test');

        $this->get('/auth/social/google/link/callback')
            ->assertRedirect('/account/security?social=conflict&provider=google');

        $this->assertSame(1, UserSocialLink::query()->where('external_id', 'ext-1')->count());
    }

    #[Test]
    public function a_link_can_be_disconnected_from_the_account_page(): void
    {
        $this->seedRoles();
        $user = $this->createAndActAs(role: 'member');
        UserSocialLink::query()->create([
            'user_id' => $user->id, 'provider' => 'google', 'external_id' => 'ext-1', 'email' => $user->email,
        ]);

        $this->getJson('/api/account/social-links')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.label', 'Google');

        // Disconnecting is a step-up action: it changes how the account signs in.
        $this->deleteJson('/api/account/social-links/google')->assertStatus(423);
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();
        $this->deleteJson('/api/account/social-links/google')->assertNoContent();
        $this->assertDatabaseCount('user_social_links', 0);
    }
}
