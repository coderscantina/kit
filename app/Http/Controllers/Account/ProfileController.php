<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Users\DeleteUser;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\UpdatePasswordRequest;
use App\Http\Requests\Account\UpdateProfileRequest;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Account\SecurityLog;
use App\Services\Account\SessionRegistry;
use App\Services\Auth\SessionBinding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): UserData
    {
        /** @var User $user */
        $user = $request->user();
        $user->fill($request->only('name', 'locale'));
        $user->save();

        return UserData::fromModel($user->load(['role', 'emailChange']));
    }

    /**
     * This browser stays signed in; every other session holds the old hash
     * and is dropped on its next request.
     */
    public function updatePassword(
        UpdatePasswordRequest $request,
        SessionBinding $binding,
        SessionRegistry $registry,
        SecurityLog $securityLog,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $user->password = $request->string('password')->toString();
        $user->save();

        $binding->rebind($request->session(), $user);

        // The other browsers are already dead on their next request; drop them
        // from the device list too, so it does not show sessions that no
        // longer work.
        $registry->revokeOthers($user, $request->session()->getId());
        $securityLog->record($user, SecurityEvent::PASSWORD_CHANGED);

        return response()->json(['message' => __('auth.password_updated')]);
    }

    public function destroy(Request $request, DeleteUser $deleteUser): Response
    {
        /** @var User $user */
        $user = $request->user();

        $deleteUser->execute($user);

        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
