<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Roles\SyncRoles;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\File;

abstract class TestCase extends BaseTestCase
{
    protected User $user;

    /**
     * Setup markers are real files on the storage volume; the registration
     * latch in particular is written by any test that exercises the gate with
     * an account present. Redirect them into a testing directory and clear
     * them per test so nothing leaks into the working tree or between tests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'kit.setup.state_path' => storage_path('app/testing/setup/install-state.json'),
            'kit.setup.registration_closed_path' => storage_path('app/testing/setup/registration-closed'),
        ]);

        $this->clearSetupMarkers();
    }

    protected function tearDown(): void
    {
        $this->clearSetupMarkers();

        parent::tearDown();
    }

    private function clearSetupMarkers(): void
    {
        File::deleteDirectory(storage_path('app/testing'));
    }

    protected function seedRoles(): void
    {
        app(SyncRoles::class)->execute();
    }

    protected function createAndActAs(?User $user = null, ?string $role = null): User
    {
        $this->user = $user ?? User::factory()->create();

        if ($role !== null) {
            $this->assignRole($this->user, $role);
        }

        $this->actingAs($this->user);

        return $this->user;
    }

    protected function assignRole(User $user, string $roleKey): void
    {
        if (! Role::query()->where('key', $roleKey)->exists()) {
            $this->seedRoles();
        }

        $user->role_id = Role::byKey($roleKey)->id;
        $user->save();
        $user->unsetRelation('role');
    }

    protected function assertRole(User $user, string $roleKey): void
    {
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role_id' => Role::byKey($roleKey)->id,
        ]);
    }
}
