<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Feature providers are discovered rather than listed: a feature is a
     * self-contained folder, and one more line in bootstrap/providers.php is
     * one more thing make:feature can get wrong.
     */
    public function register(): void
    {
        foreach (glob(app_path('Features/*/*ServiceProvider.php')) ?: [] as $file) {
            /** @var class-string<ServiceProvider> $provider */
            $provider = 'App\\Features\\'.basename(dirname($file)).'\\'.basename($file, '.php');

            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $this->configureModels();
        $this->configureRateLimiting();
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

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('app', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('ai', fn (Request $request) => Limit::perMinute((int) config('ai.limits.rate_limit', 20))->by($request->user()?->getAuthIdentifier() ?? $request->ip()));
    }
}
