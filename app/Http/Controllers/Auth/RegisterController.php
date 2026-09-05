<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Users\CreateUser;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Support\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Open self-registration, subject to the first-account latch. Invited users
 * register through the invite accept endpoint instead.
 */
class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, CreateUser $createUser): JsonResponse
    {
        if (! FeatureGate::registrationOpen()) {
            return response()->json([
                'message' => __('auth.registration_closed'),
                'error_code' => 'REGISTRATION_CLOSED',
            ], 403);
        }

        $user = $createUser->execute([
            'name' => $request->string('name')->toString(),
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
            'locale' => $request->input('locale', app()->getLocale()),
        ]);

        $user->sendEmailVerificationNotification();

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => UserData::fromModel($user)], 201);
    }
}
