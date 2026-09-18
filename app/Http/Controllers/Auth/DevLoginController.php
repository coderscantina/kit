<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\CompleteSignIn;
use App\Http\Controllers\Controller;
use App\Models\User;
use Database\Seeders\DevSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Local only: signs in as the seeded account for a role in one GET, so an
 * agent or a second browser skips the login form. routes/web.php registers it
 * only in the local environment with debug on.
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request, string $role, CompleteSignIn $completeSignIn): RedirectResponse
    {
        $user = User::query()->where('email', DevSeeder::email($role))->first();

        abort_if($user === null, 404, "No seeded account for [{$role}]. Run `php artisan db:seed`.");

        Auth::guard('web')->login($user);
        $completeSignIn->execute($request, $user);

        return redirect('/');
    }
}
