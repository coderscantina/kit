<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAppAccess;
use App\Http\Middleware\PreventDuringImpersonation;
use App\Http\Middleware\RequirePasswordVerification;
use App\Http\Middleware\RequireTotpVerification;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackUserSession;
use App\Http\Middleware\VersionHeader;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Middleware\TrustHosts;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Middleware\ValidatePathEncoding;
use Illuminate\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global stack, in this order. TrustHosts first so a forged Host header
        // never reaches URL generation; SecurityHeaders and VersionHeader early
        // so every response, including errors, carries them.
        $middleware->use([
            ValidatePathEncoding::class,
            InvokeDeferredCallbacks::class,
            TrustHosts::class,
            SecurityHeaders::class,
            VersionHeader::class,
            TrustProxies::class,
            HandleCors::class,
            PreventRequestsDuringMaintenance::class,
            ValidatePostSize::class,
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);

        $middleware->trustHosts(at: fn () => [parse_url((string) config('app.url'), PHP_URL_HOST)], subdomains: false);
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));

        // Binds each session to the password hash it was created under, so
        // changing a password logs out every other browser holding a session.
        // TrackUserSession runs behind it: the device list only means anything
        // once the session has survived the binding check.
        $middleware->web(append: [AuthenticateSession::class, TrackUserSession::class]);

        $middleware->alias([
            'app.access' => EnsureAppAccess::class,
            'password.confirmed' => RequirePasswordVerification::class,
            'totp' => RequireTotpVerification::class,
            'not-impersonating' => PreventDuringImpersonation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'auth/*', 'rq/*') || $request->expectsJson(),
        );

        $exceptions->dontFlash([
            'api_key', 'client_secret', 'current_password', 'password',
            'password_confirmation', 'secret', 'token', 'webhook_secret',
        ]);

        // A database error carries table and column names, sometimes values.
        // In production the client sees a generic message and the log keeps
        // the real one.
        $exceptions->respond(function ($response, Throwable $e, Request $request) {
            if ($e instanceof QueryException && app()->isProduction()) {
                return $request->expectsJson() || $request->is('api/*', 'auth/*', 'rq/*')
                    ? response()->json(['message' => 'A general error occurred.'], 500)
                    : response('A general error occurred.', 500);
            }

            return $response;
        });
    })->create();
