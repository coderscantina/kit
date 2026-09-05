<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\DoctorCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(DoctorCommand::class)]
final class DoctorCommandTest extends TestCase
{
    #[Test]
    public function a_healthy_checkout_passes_every_repository_check(): void
    {
        $this->artisan('kit:doctor')
            ->assertSuccessful()
            ->expectsOutputToContain('registered queries')
            ->expectsOutputToContain('i18n parity');
    }

    #[Test]
    public function an_ambient_binding_missing_from_the_octane_flush_list_fails(): void
    {
        config(['kit.ambient_bindings' => ['currentTenant']]);

        $this->artisan('kit:doctor')
            ->assertFailed()
            ->expectsOutputToContain('currentTenant');
    }

    /**
     * Redis, Horizon and Reverb being down is a laptop with services stopped,
     * not a broken repository, so it must not fail the run.
     */
    #[Test]
    public function an_unreachable_service_warns_instead_of_failing(): void
    {
        config([
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 1,
            'broadcasting.default' => 'reverb',
            'reverb.servers.reverb.host' => '127.0.0.1',
            'reverb.servers.reverb.port' => 1,
        ]);

        $this->artisan('kit:doctor')->assertSuccessful();
        $this->artisan('kit:doctor --strict')->assertFailed();
    }
}
