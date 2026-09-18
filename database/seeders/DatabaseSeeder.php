<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Roles\SyncRoles;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(SyncRoles $syncRoles): void
    {
        $syncRoles->execute();

        if (app()->environment('local')) {
            $this->call(DevSeeder::class);
        }
    }
}
