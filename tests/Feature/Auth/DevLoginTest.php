<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\DevLoginController;
use App\Models\SecurityEvent;
use App\Models\User;
use Database\Seeders\DevSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DevLoginController::class)]
#[CoversClass(DevSeeder::class)]
final class DevLoginTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function local_signs_in_as_the_seeded_account_for_a_role(): void
    {
        $this->seedRoles();
        $this->seed(DevSeeder::class);
        $this->seed(DevSeeder::class);
        $this->registerRoutesAs('local', debug: true);

        $this->assertSame(1, User::query()->where('email', 'admin@kit.test')->count());

        $this->get('/dev/login/admin')->assertRedirect('/');

        $admin = User::query()->where('email', 'admin@kit.test')->firstOrFail();
        $this->assertAuthenticatedAs($admin);
        $this->assertRole($admin, 'admin');
        $this->assertNotNull($admin->last_login_at);
        $this->assertDatabaseHas('security_events', ['user_id' => $admin->id, 'event' => SecurityEvent::SIGNED_IN]);
    }

    #[Test]
    public function an_unseeded_role_is_a_404(): void
    {
        $this->registerRoutesAs('local', debug: true);

        $this->get('/dev/login/nobody')->assertNotFound();
        $this->assertGuest();
    }

    #[Test]
    public function the_route_does_not_exist_outside_local_or_without_debug(): void
    {
        $this->seedRoles();
        $this->seed(DevSeeder::class);

        $this->get('/dev/login/admin')->assertNotFound();

        $this->registerRoutesAs('local', debug: false);
        $this->get('/dev/login/admin')->assertNotFound();

        $this->assertGuest();
    }

    /** Routes are read at boot, so load web.php again under the environment the test wants. */
    private function registerRoutesAs(string $environment, bool $debug): void
    {
        $this->app['env'] = $environment;
        config(['app.debug' => $debug]);

        Route::middleware('web')->group(base_path('routes/web.php'));
        Route::getRoutes()->refreshNameLookups();
    }
}
