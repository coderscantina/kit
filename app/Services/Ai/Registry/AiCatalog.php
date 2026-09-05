<?php

declare(strict_types=1);

namespace App\Services\Ai\Registry;

use App\Services\Ai\AiAction;
use App\Services\Ai\Attributes\AiStream;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use RuntimeException;
use Spatie\LaravelData\Data;

/**
 * Name → class map, discovered from the #[AiStream] attributes under the
 * configured directories, so an action is registered by existing rather than
 * by being added to a list.
 *
 * Memoised for the life of the process: the class list is static data, which
 * is what makes that safe under Octane.
 */
final class AiCatalog
{
    /** @var array<string, class-string<AiAction<Data>>>|null */
    private ?array $actions = null;

    /**
     * @param  array<string, string>  $discovery  namespace prefix => directory
     * @param  array<int, class-string>  $classes  explicitly registered classes
     */
    public function __construct(
        private readonly array $discovery,
        private readonly array $classes = [],
    ) {}

    /**
     * @return class-string<AiAction<Data>>|null
     */
    public function action(string $name): ?string
    {
        return $this->actions()[$name] ?? null;
    }

    /**
     * @return array<string, class-string<AiAction<Data>>>
     */
    public function actions(): array
    {
        if ($this->actions === null) {
            $this->build();
        }

        /** @var array<string, class-string<AiAction<Data>>> $actions */
        $actions = $this->actions;

        return $actions;
    }

    /**
     * Rescan. Tests and the generators reach for this after writing a class
     * the process has already scanned past.
     */
    public function reset(): void
    {
        $this->actions = null;
    }

    private function build(): void
    {
        $this->actions = [];

        foreach ([...$this->discovered(), ...$this->classes] as $class) {
            $this->register($class);
        }

        ksort($this->actions);
    }

    /**
     * @param  class-string  $class
     */
    private function register(string $class): void
    {
        if (! class_exists($class)) {
            return;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isAbstract()) {
            return;
        }

        foreach ($reflection->getAttributes(AiStream::class) as $attribute) {
            $name = $attribute->newInstance()->name;

            if (! $reflection->isSubclassOf(AiAction::class)) {
                throw new RuntimeException("{$class} carries #[AiStream] but does not extend ".AiAction::class);
            }

            if (isset($this->actions[$name])) {
                throw new RuntimeException("AI action name '{$name}' is declared twice: {$this->actions[$name]} and {$class}");
            }

            /** @var class-string<AiAction<Data>> $class */
            $this->actions[$name] = $class;
        }
    }

    /**
     * @return array<int, class-string>
     */
    private function discovered(): array
    {
        $classes = [];

        foreach ($this->discovery as $namespace => $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                /** @var class-string $class */
                $class = $namespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    ltrim(str_replace($directory, '', $file->getPathname()), '/'),
                );

                $classes[] = $class;
            }
        }

        return $classes;
    }
}
