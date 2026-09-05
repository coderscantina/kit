<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\EnsureAppAccess;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VersionHeader;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SecurityHeaders::class)]
#[CoversClass(VersionHeader::class)]
#[CoversClass(EnsureAppAccess::class)]
final class SecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_response_carries_the_security_and_version_headers(): void
    {
        config(['app.version' => '2026.9.5-abc1234']);

        $response = $this->withoutVite()->get('/');

        $response->assertOk()
            ->assertHeader('x-content-type-options', 'nosniff')
            ->assertHeader('content-security-policy', "frame-ancestors 'none'")
            ->assertHeader('x-frame-options', 'DENY')
            ->assertHeader('referrer-policy', 'strict-origin-when-cross-origin')
            ->assertHeader('x-app-version', '2026.9.5-abc1234')
            ->assertHeaderMissing('strict-transport-security');
    }

    #[Test]
    public function a_response_with_its_own_csp_is_left_alone(): void
    {
        Route::get('/api/framed', fn () => response('ok')->header('content-security-policy', "frame-ancestors 'self'"));

        $this->get('/api/framed')
            ->assertHeader('content-security-policy', "frame-ancestors 'self'")
            ->assertHeaderMissing('x-frame-options');
    }

    #[Test]
    public function the_app_access_gate_rejects_anonymous_and_roleless_users(): void
    {
        Route::middleware(['web', 'auth', 'app.access'])->get('/api/gated', fn () => 'in');

        $this->getJson('/api/gated')->assertUnauthorized();

        $this->actingAs(User::factory()->create())->getJson('/api/gated')->assertForbidden();
    }

    #[Test]
    public function the_app_access_gate_admits_a_member(): void
    {
        Route::middleware(['web', 'auth', 'app.access'])->get('/api/gated', fn () => 'in');

        $this->createAndActAs(role: 'member');

        $this->getJson('/api/gated')->assertOk();
    }

    #[Test]
    public function the_shell_injects_the_runtime_config(): void
    {
        $this->withoutVite()->get('/')
            ->assertOk()
            ->assertSee('window.__APP_CONFIG__ = {', false)
            ->assertSee('"echo":null', false)
            ->assertSee('"registration":true', false);
    }

    #[Test]
    public function api_paths_never_fall_through_to_the_shell(): void
    {
        $this->getJson('/api/nothing-here')->assertNotFound();
        $this->getJson('/rq/nothing-here')->assertNotFound();
    }
}
