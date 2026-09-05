<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\AuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed gate for every authenticated route group.
 *
 * Per-endpoint authorize() calls stay the primary layer. This exists so a
 * single forgotten check is a 403, not a data leak: unless the user holds the
 * baseline `app.access` ability, the request never reaches a controller or a
 * reactive query.
 */
class EnsureAppAccess
{
    public function __construct(
        private readonly AuthorizationService $authorization,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_if($user === null, 401);
        abort_unless($this->authorization->can($user, 'app.access'), 403);

        return $next($request);
    }
}
