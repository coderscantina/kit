<?php

declare(strict_types=1);

use App\Support\RuntimeConfigPayload;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';
require __DIR__.'/app.php';

// The SPA shell. Everything the router owns resolves here; API prefixes are
// excluded so a wrong path is a 404, not a blank page with a JSON parse error.
Route::get('/{any?}', fn () => view('app', ['config' => RuntimeConfigPayload::build()->toArray()]))
    ->where('any', '^(?!api/|auth/|rq/|up$|broadcasting/|horizon).*$')
    ->name('spa');
