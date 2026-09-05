<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * `X-App-Version` on every response. The SPA compares it to its own build
 * and offers a reload when they differ, so a deploy never leaves a tab
 * talking to an API it was not built against.
 */
class VersionHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('x-app-version', (string) config('app.version'));

        return $response;
    }
}
