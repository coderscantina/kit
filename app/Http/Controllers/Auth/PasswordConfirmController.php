<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Concerns\ThrottlesStepUpVerification;
use App\Http\Requests\Auth\ConfirmPasswordRequest;
use App\Models\User;
use App\Services\Auth\PasswordConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

/**
 * Opens the password-confirmation window that RequirePasswordVerification
 * checks before sensitive changes.
 */
class PasswordConfirmController extends Controller
{
    use ThrottlesStepUpVerification;

    public function __invoke(ConfirmPasswordRequest $request, PasswordConfirmation $confirmation): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($lockout = $this->stepUpLockout($user, 'password')) {
            return $lockout;
        }

        if (! Hash::check($request->string('password')->toString(), $user->password)) {
            $this->recordStepUpFailure($user, 'password');

            return response()->json([
                'message' => __('auth.invalid_password'),
                'error_code' => 'INVALID_PASSWORD',
            ], 403);
        }

        $this->clearStepUpAttempts($user, 'password');
        $confirmation->confirm($request->session());

        return response()->json(['message' => __('auth.password_confirmed')]);
    }
}
