<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Env;

/**
 * Fails when a required environment variable is missing. The container
 * entrypoint runs it before setup, so a half-configured deployment stops at
 * boot with a list of names instead of failing on the first request.
 */
class ConfigValidateCommand extends Command
{
    protected $signature = 'config:validate {--env-file=.env.example : Example file whose keys must all be documented}';

    protected $description = 'Verify that every required environment variable is set';

    public function handle(): int
    {
        /** @var array<string, array<int, string>> $required */
        $required = config('kit.required_env', []);

        $keys = $required['always'] ?? [];

        if (app()->isProduction()) {
            $keys = [...$keys, ...($required['production'] ?? [])];
        }

        $missing = array_values(array_filter($keys, fn (string $key) => $this->isBlank($key)));

        foreach ($this->undocumentedKeys((string) $this->option('env-file'), $keys) as $key) {
            $this->components->warn("{$key} is required but not listed in the example env file.");
        }

        if ($missing !== []) {
            $this->components->error('Missing required environment: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $this->components->info('Environment is complete ('.count($keys).' keys checked).');

        return self::SUCCESS;
    }

    private function isBlank(string $key): bool
    {
        $value = Env::get($key);

        return $value === null || $value === '';
    }

    /**
     * @param  array<int, string>  $keys
     * @return array<int, string>
     */
    private function undocumentedKeys(string $file, array $keys): array
    {
        $path = base_path($file);

        if (! is_file($path)) {
            return [];
        }

        $documented = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*#?\s*([A-Z0-9_]+)=/', $line, $m) === 1) {
                $documented[] = $m[1];
            }
        }

        return array_values(array_diff($keys, $documented));
    }
}
