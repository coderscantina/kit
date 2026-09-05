<?php

declare(strict_types=1);

namespace Kit\Reactive\Registry;

use Illuminate\Support\Facades\File;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Mutation;
use Kit\Reactive\Query;
use ReflectionClass;
use RuntimeException;

/**
 * Name → class map, discovered from the #[ReactiveQuery] / #[ReactiveMutation]
 * attributes under the configured feature directories. Memoised for the
 * life of the process; the class list is static data, so that is safe under
 * Octane.
 */
final class Catalog
{
    /** @var array<string, class-string<Query>>|null */
    private ?array $queries = null;

    /** @var array<string, class-string<Mutation>>|null */
    private ?array $mutations = null;

    /**
     * @param  array<string, string>  $discovery  namespace prefix => directory
     * @param  array<int, class-string>  $classes  explicitly registered classes
     */
    public function __construct(
        private readonly array $discovery,
        private readonly array $classes = [],
    ) {}

    /**
     * @return class-string<Query>|null
     */
    public function query(string $name): ?string
    {
        return $this->queries()[$name] ?? null;
    }

    /**
     * @return class-string<Mutation>|null
     */
    public function mutation(string $name): ?string
    {
        return $this->mutations()[$name] ?? null;
    }

    /**
     * @return array<string, class-string<Query>>
     */
    public function queries(): array
    {
        $this->load();

        /** @var array<string, class-string<Query>> $queries */
        $queries = $this->queries;

        return $queries;
    }

    /**
     * @return array<string, class-string<Mutation>>
     */
    public function mutations(): array
    {
        $this->load();

        /** @var array<string, class-string<Mutation>> $mutations */
        $mutations = $this->mutations;

        return $mutations;
    }

    public function reset(): void
    {
        $this->queries = null;
        $this->mutations = null;
    }

    private function load(): void
    {
        if ($this->queries !== null) {
            return;
        }

        $this->queries = [];
        $this->mutations = [];

        foreach ([...$this->discoveredClasses(), ...$this->classes] as $class) {
            $this->register($class);
        }
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

        foreach ($reflection->getAttributes(ReactiveQuery::class) as $attribute) {
            $name = $attribute->newInstance()->name;

            if (! $reflection->isSubclassOf(Query::class)) {
                throw new RuntimeException("{$class} carries #[ReactiveQuery] but does not extend ".Query::class);
            }

            if (isset($this->queries[$name])) {
                throw new RuntimeException("Reactive query name '{$name}' is declared twice: {$this->queries[$name]} and {$class}");
            }

            /** @var class-string<Query> $class */
            $this->queries[$name] = $class;
        }

        foreach ($reflection->getAttributes(ReactiveMutation::class) as $attribute) {
            $name = $attribute->newInstance()->name;

            if (! $reflection->isSubclassOf(Mutation::class)) {
                throw new RuntimeException("{$class} carries #[ReactiveMutation] but does not extend ".Mutation::class);
            }

            if (isset($this->mutations[$name])) {
                throw new RuntimeException("Reactive mutation name '{$name}' is declared twice: {$this->mutations[$name]} and {$class}");
            }

            /** @var class-string<Mutation> $class */
            $this->mutations[$name] = $class;
        }
    }

    /**
     * @return array<int, class-string>
     */
    private function discoveredClasses(): array
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

                $relative = substr($file->getPathname(), strlen(rtrim($directory, '/')) + 1);

                if (! str_contains($relative, '/Queries/') && ! str_contains($relative, '/Mutations/')) {
                    continue;
                }

                /** @var class-string $class */
                $class = rtrim($namespace, '\\').'\\'.str_replace('/', '\\', substr($relative, 0, -4));
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
