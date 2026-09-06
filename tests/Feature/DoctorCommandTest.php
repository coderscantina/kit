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

    #[Test]
    public function a_sync_queue_needs_no_worker(): void
    {
        $this->artisan('kit:doctor')
            ->assertSuccessful()
            ->expectsOutputToContain('jobs run inline');
    }

    /**
     * The null driver accepts the probe and drops it, which is exactly what
     * a queue with nobody consuming it looks like from the outside.
     */
    #[Test]
    public function a_queue_nobody_consumes_warns_and_names_the_worker_command(): void
    {
        config(['queue.default' => 'null']);

        // One run, not one per mode: the probe waits its full timeout here.
        $this->artisan('kit:doctor --strict')
            ->assertFailed()
            ->expectsOutputToContain('no worker took the probe');
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
