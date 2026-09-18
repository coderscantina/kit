<?php

declare(strict_types=1);

namespace Kit\Reactive;

use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Kit\Reactive\Console\CacheCommand;
use Kit\Reactive\Console\ClearCommand;
use Kit\Reactive\Console\GcCommand;
use Kit\Reactive\Console\InspectCommand;
use Kit\Reactive\Contracts\Metrics;
use Kit\Reactive\Contracts\Pusher;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Http\Controllers\ReactiveController;
use Kit\Reactive\Invalidation\ChangeBuffer;
use Kit\Reactive\Listeners\CleanupOnChannelRemoved;
use Kit\Reactive\Listeners\PurgeUserSubscriptionsOnLogout;
use Kit\Reactive\Metrics\ArrayMetrics;
use Kit\Reactive\Metrics\RedisMetrics;
use Kit\Reactive\Push\BroadcastPusher;
use Kit\Reactive\Registry\ArrayRegistry;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Registry\RedisRegistry;
use Kit\Reactive\Runtime\TableTracker;
use Laravel\Reverb\Events\ChannelRemoved;

class ReactiveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reactive.php', 'reactive');

        $this->app->singleton(Registry::class, function ($app) {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('reactive');

            return $config['registry'] === 'array'
                ? new ArrayRegistry
                : new RedisRegistry(
                    $app->make(RedisFactory::class),
                    (string) $config['redis_connection'],
                    (int) $config['ttl_seconds'],
                    (int) ($config['lock_wait_ms'] ?? 5000),
                );
        });

        $this->app->singleton(Metrics::class, function ($app) {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('reactive');

            return $config['registry'] === 'array'
                ? new ArrayMetrics
                : new RedisMetrics($app->make(RedisFactory::class), (string) $config['redis_connection']);
        });

        $this->app->singleton(Catalog::class, function ($app) {
            /** @var array<string, string> $discovery */
            $discovery = $app['config']->get('reactive.discovery', []);
            /** @var array<int, class-string> $classes */
            $classes = $app['config']->get('reactive.classes', []);
            /** @var string|null $cachePath */
            $cachePath = $app['config']->get('reactive.cache_path');

            return new Catalog($discovery, $classes, $cachePath);
        });

        // Per-request state under Octane: both are flushed before every
        // request through the octane.flush list below.
        $this->app->singleton(TableTracker::class);
        $this->app->singleton(ChangeBuffer::class, fn ($app) => new ChangeBuffer($app->make(Registry::class)));

        $this->app->bind(Pusher::class, BroadcastPusher::class);

        $this->app->booting(function (): void {
            $flush = (array) $this->app['config']->get('octane.flush', []);
            $this->app['config']->set('octane.flush', [...$flush, TableTracker::class, ChangeBuffer::class]);
        });
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/reactive.php' => config_path('reactive.php')], 'reactive-config');

        Event::listen(QueryExecuted::class, fn (QueryExecuted $event) => $this->app->make(TableTracker::class)->record($event));
        Event::listen(TransactionCommitted::class, fn (TransactionCommitted $event) => $this->app->make(ChangeBuffer::class)->onCommitted($event));
        Event::listen(TransactionRolledBack::class, fn (TransactionRolledBack $event) => $this->app->make(ChangeBuffer::class)->onRolledBack($event));
        Event::listen(Logout::class, PurgeUserSubscriptionsOnLogout::class);

        if (class_exists(ChannelRemoved::class)) {
            Event::listen(ChannelRemoved::class, CleanupOnChannelRemoved::class);
        }

        $this->registerRateLimiters();
        $this->registerRoutes();
        $this->registerChannel();

        // A boot outside Octane otherwise walks every feature directory.
        $this->optimizes(optimize: 'reactive:cache', clear: 'reactive:clear', key: 'reactive');

        if ($this->app->runningInConsole()) {
            $this->commands([CacheCommand::class, ClearCommand::class, GcCommand::class, InspectCommand::class]);
        }
    }

    private function registerRateLimiters(): void
    {
        $by = fn (Request $request) => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());

        RateLimiter::for('reactive', fn (Request $request) => Limit::perMinute((int) config('reactive.rate_limits.default', 120))->by($by($request)));
        RateLimiter::for('reactive-query', fn (Request $request) => Limit::perMinute((int) config('reactive.rate_limits.query', 600))->by($by($request)));
    }

    private function registerRoutes(): void
    {
        /** @var array<int, string> $middleware */
        $middleware = config('reactive.middleware', ['web', 'auth']);

        Route::prefix('rq')->name('rq.')->group(function () use ($middleware): void {
            Route::middleware([...$middleware, 'throttle:reactive'])->group(function (): void {
                Route::post('subscribe', [ReactiveController::class, 'subscribe'])->name('subscribe');
                Route::post('unsubscribe', [ReactiveController::class, 'unsubscribe'])->name('unsubscribe');
                Route::post('mutate', [ReactiveController::class, 'mutate'])->name('mutate');
            });

            Route::middleware([...$middleware, 'throttle:reactive-query'])
                ->post('query', [ReactiveController::class, 'query'])
                ->name('query');

            // Authenticated: the counts and the p95 describe the registry's
            // internals and are nobody's business from the outside.
            Route::middleware([...$middleware, 'throttle:reactive'])
                ->get('health', [ReactiveController::class, 'health'])
                ->name('health');
        });
    }

    private function registerChannel(): void
    {
        Broadcast::channel('subscription.{id}', function (Authenticatable $user, string $id): bool {
            $subscription = $this->app->make(Registry::class)->get($id);

            return $subscription !== null && $subscription->userId === (string) $user->getAuthIdentifier();
        });
    }
}
