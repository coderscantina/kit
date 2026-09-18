<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\DevLoginController;
use App\Support\RuntimeConfigPayload;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/app.php';

// Sign in as a seeded account (database/seeders/DevSeeder.php). Needs both
// local and debug, so a stray APP_ENV=local alone does not open it.
if (app()->environment('local') && config('app.debug') === true) {
    Route::get('dev/login/{role}', DevLoginController::class)->name('dev.login');
}

// The SPA shell. Everything the router owns resolves here; API prefixes are
// excluded so a wrong path is a 404, not a blank page with a JSON parse error.
Route::get('/{any?}', fn () => view('app', ['config' => RuntimeConfigPayload::build()->toArray()]))
    ->where('any', '^(?!api/|auth/|dev/|rq/|up$|broadcasting/|horizon).*$')
    ->name('spa');
