<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Jobs\QueuedJob;
use App\Support\Ambient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Throwable;

#[CoversClass(Ambient::class)]
#[CoversClass(QueuedJob::class)]
final class AmbientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['kit.ambient_bindings' => ['currentTenant']]);
    }

    #[Test]
    public function enter_restores_the_previous_binding_even_when_the_callback_throws(): void
    {
        app()->instance('currentTenant', 'before');

        try {
            Ambient::enter('currentTenant', 'inside', function (): void {
                $this->assertSame('inside', app('currentTenant'));
                throw new \RuntimeException('boom');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('before', app('currentTenant'));
    }

    #[Test]
    public function enter_unbinds_a_key_that_was_not_bound_before(): void
    {
        Ambient::enter('currentTenant', 'x', fn () => null);

        $this->assertFalse(app()->bound('currentTenant'));
    }

    #[Test]
    public function a_job_cannot_leak_ambient_state_into_the_next_one(): void
    {
        $job = new class extends QueuedJob
        {
            protected function execute(): void
            {
                app()->instance('currentTenant', 'leaked');
            }

            protected function handleFailure(Throwable $e): void {}
        };

        $job->handle();

        $this->assertFalse(app()->bound('currentTenant'));
    }
}
