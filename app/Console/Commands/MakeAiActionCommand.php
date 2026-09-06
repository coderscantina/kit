<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\Commands\Concerns\ResolvesFeatureNames;
use App\Console\Commands\Concerns\WritesStubs;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Creates a streamed AI action, the args class it validates through, and a
 * feature test that runs it against a faked model.
 *
 * Every action lands in app/Ai/Actions, next to its args class in app/Data:
 * an AI action is application code with a different output device, and there
 * is one endpoint for all of them.
 */
class MakeAiActionCommand extends Command
{
    use ResolvesFeatureNames;
    use WritesStubs;

    protected $signature = 'make:ai-action {name : The client-facing name, e.g. posts.summarize}';

    protected $description = 'Create a streamed AI action, its args class and its feature test';

    public function handle(): int
    {
        $name = (string) $this->argument('name');

        try {
            [$segment, $verb] = $this->splitReactiveName($name);
            $names = $this->existingFeature($segment);
        } catch (RuntimeException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $feature = $names['feature'];
        $class = Str::studly($verb).$feature;

        $replacements = [
            ...$names,
            'name' => $name,
            'class' => $class,
            'args' => "{$class}Args",
        ];

        $this->writeStub($this->stub('ai.args'), app_path("Data/{$class}Args.php"), [...$replacements, 'class' => "{$class}Args"]);
        $this->writeStub($this->stub('ai.action'), app_path("Ai/Actions/{$class}.php"), $replacements);
        $this->writeStub($this->stub('ai.action.test'), base_path("tests/Feature/{$feature}/{$class}Test.php"), $replacements);

        $this->newLine();
        $this->components->info("AI action {$name} created. Run `php artisan types:generate` to type it on the client.");

        return self::SUCCESS;
    }
}
