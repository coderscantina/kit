<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
        $this->configureAccessTokens();
    }

    /**
     * Strict in every environment, production included. Running strict only
     * in development means a non-fillable mass assignment throws on a laptop
     * and is silently dropped on the server; that trap is worth the small
     * cost of the checks.
     */
    private function configureModels(): void
    {
        Model::preventSilentlyDiscardingAttributes();
        Model::preventAccessingMissingAttributes();
        Model::preventLazyLoading(! $this->app->isProduction());
    }

    /**
     * `auth:sanctum` accepts a bearer token on every route it guards, the SPA's
     * own API included. A personal access token is meant for the MCP endpoint
     * only, so everywhere else it does not authenticate: a leaked token cannot
     * export the account's data or read its sessions.
     */
    private function configureAccessTokens(): void
    {
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid): bool => $isValid && request()->routeIs('mcp'),
        );
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('app', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute((int) config('ai.limits.rate_limit', 20))->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
