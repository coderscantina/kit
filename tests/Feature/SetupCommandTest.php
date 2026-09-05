<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ConfigValidateCommand;
use App\Console\Commands\SetupCommand;
use App\Models\Role;
use App\Models\User;
use App\Support\FeatureGate;
use App\Support\InstallState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SetupCommand::class)]
#[CoversClass(ConfigValidateCommand::class)]
#[CoversClass(InstallState::class)]
#[CoversClass(FeatureGate::class)]
final class SetupCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function setup_is_idempotent_and_version_aware(): void
    {
        config(['app.version' => '1']);

        $this->artisan('kit:setup')->assertSuccessful()->expectsOutputToContain('Installed version 1');
        $this->assertSame(3, Role::query()->count());
        $this->assertSame('1', app(InstallState::class)->recordedVersion());

        $this->artisan('kit:setup')->assertSuccessful()->expectsOutputToContain('nothing to do');

        config(['app.version' => '2']);
        $this->artisan('kit:setup')->assertSuccessful()->expectsOutputToContain('Upgraded from 1 to 2');
        $this->assertSame('2', app(InstallState::class)->recordedVersion());
    }

    #[Test]
    public function registration_latches_shut_once_an_account_exists(): void
    {
        config(['features.registration' => null]);

        $this->assertTrue(FeatureGate::registrationOpen());

        User::factory()->create();

        $this->assertFalse(FeatureGate::registrationOpen());
        $this->assertTrue(app(InstallState::class)->registrationClosed());

        User::query()->delete();

        $this->assertFalse(FeatureGate::registrationOpen(), 'the marker keeps it closed after the last account is gone');

        config(['features.registration' => 'true']);
        $this->assertTrue(FeatureGate::registrationOpen());
    }

    #[Test]
    public function config_validate_reports_missing_keys(): void
    {
        config(['kit.required_env' => ['always' => ['APP_KEY', 'DEFINITELY_NOT_SET_9'], 'production' => []]]);

        $this->artisan('config:validate')
            ->assertFailed()
            ->expectsOutputToContain('DEFINITELY_NOT_SET_9');

        config(['kit.required_env' => ['always' => ['APP_KEY'], 'production' => []]]);

        $this->artisan('config:validate')->assertSuccessful();
    }
}
