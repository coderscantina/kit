<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Http\Controllers\Account\SessionController;
use App\Http\Middleware\TrackUserSession;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Account\SessionRegistry;
use App\Support\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The endpoints are exercised over HTTP. Anything that turns on *which*
 * browser is asking goes through the registry and the middleware directly:
 * the test client starts a fresh session on every request, so two requests
 * can never stand in for one browser coming back.
 */
#[CoversClass(SessionController::class)]
#[CoversClass(SessionRegistry::class)]
#[CoversClass(TrackUserSession::class)]
final class SessionTest extends TestCase
{
    use RefreshDatabase;

    /** Session ids are 40 alphanumeric characters; anything else is regenerated. */
    private const string THIS_BROWSER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    /** A request carrying a real session with a known id. */
    private function requestFor(string $sessionId): Request
    {
        $request = Request::create('/api/account/sessions', server: [
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone) AppleWebKit Safari/605.1',
        ]);

        $request->setLaravelSession(new Store('kit-session', new ArraySessionHandler(120), $sessionId));

        return $request;
    }

    private function seedSession(User $user, string $key, string $agent = 'Mozilla/5.0 (Macintosh) Firefox/130.0'): UserSession
    {
        return UserSession::query()->create([
            'id' => $key,
            'user_id' => $user->id,
            'ip_address' => '198.51.100.4',
            'user_agent' => $agent,
            'last_active_at' => now()->subMinutes(5),
            'created_at' => now()->subDay(),
        ]);
    }

    /**
     * Index decoded rows by one of their fields. Typed, so the assertions do
     * not have to work through mixed.
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexBy(mixed $rows, string $field): array
    {
        $this->assertIsArray($rows);

        $indexed = [];

        foreach ($rows as $row) {
            $this->assertIsArray($row);
            $indexed[(string) $row[$field]] = $row;
        }

        return $indexed;
    }

    #[Test]
    public function the_list_describes_each_device_and_marks_the_one_asking(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $this->seedSession($user, UserSession::key('other-browser'), 'Mozilla/5.0 (iPhone) AppleWebKit Safari/605.1');

        $rows = $this->indexBy($this->getJson('/api/account/sessions')->assertOk()->assertJsonCount(2)->json(), 'ipAddress');

        $this->assertSame('Safari on iPhone', $rows['198.51.100.4']['device']);
        $this->assertFalse($rows['198.51.100.4']['current']);

        // The row the request itself created is the current one.
        $current = array_filter($rows, fn (array $row): bool => (bool) $row['current']);
        $this->assertCount(1, $current, 'Exactly one row, the requesting browser, is marked current.');
    }

    #[Test]
    public function the_list_never_leaks_another_account(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $this->seedSession(User::factory()->create(), UserSession::key('stranger'));

        $rows = $this->getJson('/api/account/sessions')->assertOk()->json();

        // Only this request's own row: the stranger's device stays invisible.
        $this->assertCount(1, $rows);
        $this->assertNotSame(UserSession::key('stranger'), $rows[0]['id']);
    }

    #[Test]
    public function revoking_needs_step_up_and_only_reaches_this_account(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $mine = $this->seedSession($user, UserSession::key('other-browser'));
        $theirs = $this->seedSession(User::factory()->create(), UserSession::key('stranger'));

        $this->deleteJson('/api/account/sessions')->assertStatus(423);
        $this->assertNull($mine->fresh()?->revoked_at);

        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();
        $this->deleteJson('/api/account/sessions')->assertOk();

        $this->assertNotNull($mine->fresh()?->revoked_at);
        $this->assertNull($theirs->fresh()?->revoked_at, 'Revoking must not reach another account.');
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => SecurityEvent::SESSION_REVOKED]);
    }

    #[Test]
    public function revoking_a_session_that_is_not_yours_is_a_404(): void
    {
        $this->createAndActAs(role: 'member');
        $theirs = $this->seedSession(User::factory()->create(), UserSession::key('stranger'));
        $this->postJson('/auth/password/confirm', ['password' => 'password'])->assertOk();

        $this->deleteJson("/api/account/sessions/{$theirs->id}")->assertNotFound();
        $this->assertNull($theirs->fresh()?->revoked_at);
    }

    #[Test]
    public function revoke_others_spares_the_browser_that_asked(): void
    {
        $user = User::factory()->create();
        $registry = app(SessionRegistry::class);

        $registry->track($this->requestFor(self::THIS_BROWSER), $user);
        $other = $this->seedSession($user, UserSession::key('other-browser'));

        $this->assertSame(1, $registry->revokeOthers($user, self::THIS_BROWSER));

        $this->assertNotNull($other->fresh()?->revoked_at);
        $this->assertNull(UserSession::query()->find(UserSession::key(self::THIS_BROWSER))?->revoked_at);
    }

    #[Test]
    public function tracking_records_the_device_once_and_then_only_refreshes_it(): void
    {
        $user = User::factory()->create();
        $registry = app(SessionRegistry::class);

        $registry->track($this->requestFor(self::THIS_BROWSER), $user);
        $registry->track($this->requestFor(self::THIS_BROWSER), $user);

        $this->assertDatabaseCount('user_sessions', 1);

        $row = UserSession::query()->findOrFail(UserSession::key(self::THIS_BROWSER));
        $this->assertSame('203.0.113.7', $row->ip_address);
        $this->assertSame('Safari on iPhone', UserAgent::describe($row->user_agent));

        $registry->forget($this->requestFor(self::THIS_BROWSER));
        $this->assertDatabaseEmpty('user_sessions');
    }

    #[Test]
    public function a_revoked_browser_is_torn_down_on_its_next_request(): void
    {
        $user = $this->createAndActAs(role: 'member');
        $registry = app(SessionRegistry::class);
        $request = $this->requestFor(self::THIS_BROWSER);

        $registry->track($request, $user);
        UserSession::query()->whereKey(UserSession::key(self::THIS_BROWSER))->update(['revoked_at' => now()]);

        $response = app(TrackUserSession::class)->handle(
            $this->requestFor(self::THIS_BROWSER),
            fn (): never => $this->fail('A revoked session must not reach the route.'),
        );

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('SESSION_REVOKED', (string) $response->getContent());
        $this->assertDatabaseEmpty('user_sessions');
    }

    #[Test]
    public function a_live_browser_passes_straight_through(): void
    {
        $user = $this->createAndActAs(role: 'member');

        $response = app(TrackUserSession::class)->handle(
            $this->requestFor(self::THIS_BROWSER),
            fn (): Response => response('ok'),
        );

        $this->assertSame('ok', $response->getContent());
        $this->assertDatabaseHas('user_sessions', ['id' => UserSession::key(self::THIS_BROWSER), 'user_id' => $user->id]);
    }
}
