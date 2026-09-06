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
use Spatie\LaravelData\Data;

/**
 * Name → class map, discovered from the #[ReactiveQuery] / #[ReactiveMutation]
 * attributes under the configured directories. Memoised for the life of the
 * process; the class list is static data, so that is safe under Octane. A
 * name that is not in the map reads the disk once more before it is called
 * missing, which is what keeps a worker that outlived a deploy from
 * revoking the subscribers of a query it has simply never seen.
 *
 * Scanning every file on boot costs a directory walk per request
 * outside Octane, so `php artisan reactive:cache` writes the two maps to a
 * file and this reads it when it is there. `optimize` and `optimize:clear`
 * run it.
 */
final class Catalog
{
    /** @var array<string, class-string<Query<Data>>>|null */
    private ?array $queries = null;

    /** @var array<string, class-string<Mutation<Data>>>|null */
    private ?array $mutations = null;

    private bool $useCache = true;

    private float $scannedAt = 0.0;

    /**
     * @param  array<string, string>  $discovery  namespace prefix => directory
     * @param  array<int, class-string>  $classes  explicitly registered classes
     * @param  string|null  $cachePath  the file reactive:cache writes, if any
     * @param  float  $rescanSeconds  how long a miss is trusted before the disk is read again
     */
    public function __construct(
        private readonly array $discovery,
        private readonly array $classes = [],
        private readonly ?string $cachePath = null,
        private readonly float $rescanSeconds = 1.0,
    ) {}

    /**
     * @return class-string<Query<Data>>|null
     */
    public function query(string $name): ?string
    {
        $found = $this->queries()[$name] ?? null;

        if ($found === null && $this->rescan()) {
            $found = $this->queries()[$name] ?? null;
        }

        return $found;
    }

    /**
     * @return class-string<Mutation<Data>>|null
     */
    public function mutation(string $name): ?string
    {
        $found = $this->mutations()[$name] ?? null;

        if ($found === null && $this->rescan()) {
            $found = $this->mutations()[$name] ?? null;
        }

        return $found;
    }

    /**
     * @return array<string, class-string<Query<Data>>>
     */
    public function queries(): array
    {
        $this->load();

        /** @var array<string, class-string<Query<Data>>> $queries */
        $queries = $this->queries;

        return $queries;
    }

    /**
     * @return array<string, class-string<Mutation<Data>>>
     */
    public function mutations(): array
    {
        $this->load();

        /** @var array<string, class-string<Mutation<Data>>> $mutations */
        $mutations = $this->mutations;

        return $mutations;
    }

    /**
     * Rescan. Callers reach for this after registering classes at runtime
     * (tests, the generators), so the cached map is stale from here on.
     */
    public function reset(): void
    {
        $this->useCache = false;
        $this->queries = null;
        $this->mutations = null;
    }

    /**
     * The maps as a plain array, always freshly scanned. What reactive:cache
     * writes to disk.
     *
     * @return array{queries: array<string, class-string<Query<Data>>>, mutations: array<string, class-string<Mutation<Data>>>}
     */
    public function scan(): array
    {
        $this->queries = null;
        $this->mutations = null;
        $this->build();

        /** @var array<string, class-string<Query<Data>>> $queries */
        $queries = $this->queries;
        /** @var array<string, class-string<Mutation<Data>>> $mutations */
        $mutations = $this->mutations;

        return ['queries' => $queries, 'mutations' => $mutations];
    }

    private function load(): void
    {
        if ($this->queries !== null) {
            return;
        }

        if ($this->useCache && $this->loadFromCache()) {
            return;
        }

        $this->build();
    }

    /**
     * A name this process has never seen may be new rather than gone: a
     * worker that booted before the deploy carries the map from before it,
     * and RecomputeComputation reads a miss as "the query was deleted" and
     * revokes every subscriber of it for good. So a miss reads the disk once
     * more before it is believed. Throttled, because /rq/subscribe with a
     * name that was never real reaches here too.
     */
    private function rescan(): bool
    {
        if ((microtime(true) - $this->scannedAt) < $this->rescanSeconds) {
            return false;
        }

        $this->scan();

        return true;
    }

    private function build(): void
    {
        $this->queries = [];
        $this->mutations = [];

        foreach ([...$this->discoveredClasses(), ...$this->classes] as $class) {
            $this->register($class);
        }

        $this->scannedAt = microtime(true);
    }

    private function loadFromCache(): bool
    {
        if ($this->cachePath === null || ! is_file($this->cachePath)) {
            return false;
        }

        /** @var array{queries?: array<string, class-string<Query<Data>>>, mutations?: array<string, class-string<Mutation<Data>>>} $cached */
        $cached = require $this->cachePath;

        $this->queries = $cached['queries'] ?? [];
        $this->mutations = $cached['mutations'] ?? [];

        return true;
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

            /** @var class-string<Query<Data>> $class */
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

            /** @var class-string<Mutation<Data>> $class */
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

                /** @var class-string $class */
                $class = rtrim($namespace, '\\').'\\'.str_replace('/', '\\', substr($relative, 0, -4));
                $classes[] = $class;
            }
        }

        return $classes;
    }
}
