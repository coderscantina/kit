<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Services\Ai\Dto\AiModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The provider's model list, cached.
 *
 * OpenRouter offers hundreds of models and changes them weekly, so the list
 * is fetched rather than hard-coded, and cached for half a day because a
 * cold fetch would otherwise sit in front of the first stream of the day.
 * An unreachable provider yields an empty catalogue, never an exception: not
 * knowing a model's capabilities is a reason to send a conservative request,
 * not a reason to fail one.
 */
final class ModelCatalog
{
    private const string CACHE_KEY = 'ai.models.openrouter';

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    /**
     * @return array<string, AiModel>
     */
    public function all(): array
    {
        /** @var array<string, AiModel> */
        return Cache::remember(
            self::CACHE_KEY,
            (int) config('ai.models_cache_ttl', 43200),
            fn (): array => $this->fetch(),
        );
    }

    public function find(string $id): ?AiModel
    {
        return $this->all()[$id] ?? null;
    }

    /**
     * @return array<string, AiModel>
     */
    public function refresh(): array
    {
        Cache::forget(self::CACHE_KEY);

        return $this->all();
    }

    /**
     * @return array<string, AiModel>
     */
    private function fetch(): array
    {
        $key = (string) ($this->config['api_key'] ?? '');

        if ($key === '') {
            return [];
        }

        try {
            $response = Http::withToken($key)
                ->timeout(30)
                ->get(rtrim((string) $this->config['base_url'], '/').'/models');

            if (! $response->successful()) {
                Log::warning('AI model catalogue unavailable', ['status' => $response->status()]);

                return [];
            }

            $models = [];

            /** @var array<int, array<string, mixed>> $entries */
            $entries = $response->json('data') ?? [];

            foreach ($entries as $entry) {
                if (! isset($entry['id'])) {
                    continue;
                }

                $model = AiModel::fromPayload($entry);
                $models[$model->id] = $model;
            }

            ksort($models);

            return $models;
        } catch (Throwable $e) {
            Log::warning('AI model catalogue fetch failed', ['exception' => $e]);

            return [];
        }
    }
}
