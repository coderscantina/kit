<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline response headers for the whole application.
 *
 * The console is a session-authenticated SPA, so the important one is
 * `frame-ancestors 'none'`: without it any site can frame the app and
 * clickjack a logged-in owner into a destructive action. The rest limits the
 * blast radius of an HTML-injection bug elsewhere.
 *
 * A response that ships its own content-security-policy has already decided
 * who may frame it and is left alone.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('x-content-type-options', 'nosniff', false);
        $headers->set('referrer-policy', 'strict-origin-when-cross-origin', false);

        if (! $headers->has('content-security-policy')) {
            $headers->set('content-security-policy', "frame-ancestors 'none'");
            $headers->set('x-frame-options', 'DENY', false);
        }

        if ($request->isSecure()) {
            $headers->set('strict-transport-security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
