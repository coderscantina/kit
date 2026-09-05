<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\PasswordConfirmation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up for changing email, password, second factors, or deleting the
 * account: the password must have been re-entered within the confirmation
 * window (POST /auth/password/confirm). Otherwise 423 with an error code the
 * SPA turns into a confirmation dialog.
 */
class RequirePasswordVerification
{
    public function __construct(
        private readonly PasswordConfirmation $confirmation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return response()->json(['message' => __('auth.unauthenticated')], 401);
        }

        if (! $this->confirmation->isFresh($request->session())) {
            return response()->json([
                'message' => __('auth.password_confirmation_required'),
                'error_code' => 'PASSWORD_CONFIRMATION_REQUIRED',
            ], 423);
        }

        return $next($request);
    }
}
