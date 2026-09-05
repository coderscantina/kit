<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An operator inside an impersonation session is not the account owner and
 * must not be able to change the owner's credentials or second factors.
 */
class PreventDuringImpersonation
{
    public function __construct(
        private readonly ImpersonationService $impersonation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonation->isImpersonating($request->session())) {
            return response()->json([
                'message' => __('auth.not_while_impersonating'),
                'error_code' => 'IMPERSONATING',
            ], 403);
        }

        return $next($request);
    }
}
