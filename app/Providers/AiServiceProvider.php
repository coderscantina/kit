<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Ai\Contracts\AiDriver;
use App\Services\Ai\Drivers\OpenRouterDriver;
use App\Services\Ai\Exceptions\AiException;
use App\Services\Ai\ModelCatalog;
use App\Services\Ai\Registry\AiCatalog;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the AI layer: the action catalogue, the model catalogue and the one
 * driver behind the AiDriver contract.
 *
 * Resolved lazily. An installation with no API key boots exactly as before
 * and only hears about it when something actually asks for a stream.
 */
class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AiCatalog::class, fn (): AiCatalog => new AiCatalog(
            discovery: (array) config('ai.discovery', []),
            classes: (array) config('ai.classes', []),
        ));

        $this->app->singleton(ModelCatalog::class, fn (): ModelCatalog => new ModelCatalog(
            $this->driverConfig(),
        ));

        $this->app->singleton(AiDriver::class, function (): AiDriver {
            $driver = (string) config('ai.driver');

            return match ($driver) {
                'openrouter' => new OpenRouterDriver($this->driverConfig(), $this->app->make(ModelCatalog::class)),
                default => throw new AiException(
                    AiException::REASON_NOT_CONFIGURED,
                    "Unknown AI driver '{$driver}'.",
                    500,
                ),
            };
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function driverConfig(): array
    {
        return (array) config('ai.drivers.'.config('ai.driver'), []);
    }
}
