<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Roles\SyncRoles;
use App\Support\InstallState;
use Illuminate\Console\Command;

/**
 * Idempotent first run and upgrade. Migrates, syncs roles and records the
 * running version; a second call on the same version is a no-op, and a call
 * after an image upgrade applies the new migrations.
 */
class SetupCommand extends Command
{
    protected $signature = 'kit:setup {--force : Migrate and sync roles even when the recorded version matches}';

    protected $description = 'Migrate, seed roles and record the install state (safe to run on every boot)';

    public function handle(InstallState $state, SyncRoles $syncRoles): int
    {
        $version = (string) config('app.version');
        $recorded = $state->recordedVersion();

        if ($state->exists() && $recorded === $version && ! $this->option('force')) {
            $this->components->info("Already set up at version {$version}; nothing to do.");

            return self::SUCCESS;
        }

        $this->components->task('Running migrations', fn () => $this->callSilently('migrate', ['--force' => true]) === self::SUCCESS);
        $this->components->task('Syncing roles', function () use ($syncRoles) {
            $syncRoles->execute();

            return true;
        });
        $this->components->task('Recording install state', function () use ($state, $version) {
            $state->write($version);

            return true;
        });

        $this->components->info($recorded === null
            ? "Installed version {$version}."
            : "Upgraded from {$recorded} to {$version}.");

        return self::SUCCESS;
    }
}
