<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ai\ModelCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Shows the provider's catalogue, which is how a model slug in .env gets
 * checked before it fails in front of a user.
 */
class AiModelsCommand extends Command
{
    protected $signature = 'ai:models {--refresh : Fetch the catalogue again instead of reading the cache}
                                     {--search= : Only models whose id or name contains this}
                                     {--tools : Only models that support tool calling}';

    protected $description = 'List the AI models the provider offers, with context window and price';

    public function handle(ModelCatalog $catalog): int
    {
        $models = $this->option('refresh') ? $catalog->refresh() : $catalog->all();

        if ($models === []) {
            $this->components->error('The provider returned no models. Check OPENROUTER_API_KEY and the connection.');

            return self::FAILURE;
        }

        $search = (string) ($this->option('search') ?? '');

        $rows = [];

        foreach ($models as $model) {
            if ($search !== '' && ! Str::contains($model->id.' '.$model->name, $search, ignoreCase: true)) {
                continue;
            }

            if ($this->option('tools') && ! $model->supportsTools) {
                continue;
            }

            $rows[] = [
                $model->id,
                number_format($model->contextWindow),
                '$'.number_format($model->inputCost, 2),
                '$'.number_format($model->outputCost, 2),
                implode(', ', array_diff($model->capabilities, ['text'])) ?: '-',
            ];
        }

        if ($rows === []) {
            $this->components->warn('No model matches.');

            return self::SUCCESS;
        }

        $this->table(['Model', 'Context', 'In / Mtok', 'Out / Mtok', 'Extras'], $rows);

        $configured = (string) config('ai.model');

        $this->newLine();

        isset($models[$configured])
            ? $this->components->info("Default model: {$configured}")
            : $this->components->warn("Default model '{$configured}' is not in the catalogue; set AI_MODEL to one of the above.");

        return self::SUCCESS;
    }
}
