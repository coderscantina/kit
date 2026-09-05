<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Support\Facades\File;
use Kit\Reactive\Attributes\ReactiveMutation;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Contracts\Registry;
use Kit\Reactive\Invalidation\ChangeBuffer;
use Kit\Reactive\Registry\Catalog;
use Kit\Reactive\Runtime\TableTracker;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use ReflectionClass;
use SplFileInfo;
use Throwable;

/**
 * The checks that catch the failures nothing else does: a query the client
 * calls but the server never registered, a test that silently stopped
 * running, an ambient binding that leaks between Octane requests.
 *
 * Reachability problems are warnings, because a laptop with Redis stopped is
 * not a broken repository. Everything the repository itself controls is a
 * failure. `--strict` promotes warnings for CI.
 */
class DoctorCommand extends Command
{
    private const string OK = 'ok';

    private const string WARN = 'warn';

    private const string FAIL = 'fail';

    protected $signature = 'kit:doctor {--strict : Treat warnings as failures}';

    protected $description = 'Check the reactive runtime, the registry, the Octane flush list and the message files';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkRedis();
        $this->checkHorizon();
        $this->checkReverb();
        $this->checkRegistry();
        $this->checkUnregisteredQueries();
        $this->checkOctaneFlushList();
        $this->checkMessageParity();

        $this->newLine();

        foreach ($this->results as [$status, $name, $message]) {
            match ($status) {
                self::OK => $this->components->twoColumnDetail("<fg=green>PASS</> {$name}", $message),
                self::WARN => $this->components->twoColumnDetail("<fg=yellow>WARN</> {$name}", $message),
                default => $this->components->twoColumnDetail("<fg=red>FAIL</> {$name}", $message),
            };
        }

        $failed = $this->count(self::FAIL) + ($this->option('strict') ? $this->count(self::WARN) : 0);

        $this->newLine();

        if ($failed > 0) {
            $this->components->error("{$failed} check(s) failed.");

            return self::FAILURE;
        }

        $this->components->info('All checks passed'.($this->count(self::WARN) > 0 ? ' ('.$this->count(self::WARN).' warning(s)).' : '.'));

        return self::SUCCESS;
    }

    private function checkRedis(): void
    {
        try {
            $connection = (string) config('reactive.redis_connection', 'default');
            app(RedisFactory::class)->connection($connection)->command('ping', []);
            $this->pass('redis', "connection '{$connection}' answers PING");
        } catch (Throwable $e) {
            $this->flag('redis', 'unreachable: '.$this->short($e));
        }
    }

    private function checkHorizon(): void
    {
        try {
            $masters = app(MasterSupervisorRepository::class)->all();

            count($masters) > 0
                ? $this->pass('horizon', count($masters).' master supervisor(s) running')
                : $this->flag('horizon', 'no master supervisor is running');
        } catch (Throwable $e) {
            $this->flag('horizon', 'cannot be reached: '.$this->short($e));
        }
    }

    private function checkReverb(): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            $this->pass('reverb', 'broadcasting is '.((string) config('broadcasting.default') ?: 'null').'; realtime is off by configuration');

            return;
        }

        $host = (string) config('reverb.servers.reverb.host', '127.0.0.1');
        $port = (int) config('reverb.servers.reverb.port', 8080);
        $socket = @fsockopen($host === '0.0.0.0' ? '127.0.0.1' : $host, $port, $code, $message, 1.0);

        if ($socket === false) {
            $this->flag('reverb', "no listener on {$host}:{$port} ({$message})");

            return;
        }

        fclose($socket);
        $this->pass('reverb', "listening on {$host}:{$port}");
    }

    private function checkRegistry(): void
    {
        try {
            $registry = app(Registry::class);
            $stats = $registry->stats();
            $removed = $registry->gc();

            $removed > 0
                ? $this->flag('registry', "{$stats['subscriptions']} subscription(s); swept {$removed} orphaned dependency entries")
                : $this->pass('registry', "{$stats['subscriptions']} subscription(s), no orphaned dependency entries");
        } catch (Throwable $e) {
            $this->flag('registry', 'cannot be read: '.$this->short($e));
        }
    }

    /**
     * Two ways a name goes missing: a class under Queries/ or Mutations/
     * without its attribute (the catalog never sees it), and a name the
     * client calls that the catalog does not have.
     */
    private function checkUnregisteredQueries(): void
    {
        $catalog = app(Catalog::class);
        $known = [...array_keys($catalog->queries()), ...array_keys($catalog->mutations())];

        $unattributed = [];

        foreach ($this->featureFiles() as $file) {
            $class = $this->classFromFile($file);

            if ($class === null || ! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            if ($reflection->getAttributes(ReactiveQuery::class) === [] && $reflection->getAttributes(ReactiveMutation::class) === []) {
                $unattributed[] = $class;
            }
        }

        $unattributed === []
            ? $this->pass('registered queries', count($known).' name(s) registered, every class carries its attribute')
            : $this->broke('registered queries', 'missing #[ReactiveQuery]/#[ReactiveMutation]: '.implode(', ', $unattributed));

        $missing = [];

        foreach (File::allFiles(resource_path('js')) as $file) {
            if (! in_array($file->getExtension(), ['ts', 'vue'], true)) {
                continue;
            }

            preg_match_all("/useReactive(?:Query|Mutation)\(\s*'([^']+)'/", $file->getContents(), $matches);

            foreach ($matches[1] as $name) {
                if (! in_array($name, $known, true)) {
                    $missing[] = $name.' ('.$this->relative($file->getPathname()).')';
                }
            }
        }

        $missing === []
            ? $this->pass('client query names', 'every name the client calls exists on the server')
            : $this->broke('client query names', 'not registered: '.implode(', ', array_unique($missing)));
    }

    /**
     * Not a real Octane boot: booting a worker from a doctor run means
     * binding a port and waiting on it, which fails for the wrong reasons on
     * a busy machine. The list is what actually decides the outcome, so it is
     * what gets checked. `bin/gate` and the compose smoke exercise the real
     * worker.
     */
    private function checkOctaneFlushList(): void
    {
        /** @var array<int, string> $flush */
        $flush = config('octane.flush', []);

        /** @var array<int, string> $ambient */
        $ambient = config('kit.ambient_bindings', []);

        $required = [...$ambient, TableTracker::class, ChangeBuffer::class];
        $leaking = array_values(array_diff($required, $flush));

        $leaking === []
            ? $this->pass('octane flush list', count($required).' per-request binding(s) are flushed (list check, not a live worker)')
            : $this->broke('octane flush list', 'would survive into the next request: '.implode(', ', $leaking));
    }

    private function checkMessageParity(): void
    {
        $en = $this->messageKeys('en');
        $de = $this->messageKeys('de');

        $missing = array_values(array_diff($en, $de));
        $extra = array_values(array_diff($de, $en));

        if ($missing === [] && $extra === []) {
            $this->pass('i18n parity', count($en).' key(s), en and de agree');

            return;
        }

        $this->broke('i18n parity', trim(
            ($missing === [] ? '' : 'missing from de: '.implode(', ', array_slice($missing, 0, 10)).' ')
            .($extra === [] ? '' : 'not in en: '.implode(', ', array_slice($extra, 0, 10)))
        ));
    }

    /**
     * @return array<int, string>
     */
    private function messageKeys(string $locale): array
    {
        /** @var array<string, mixed> $tree */
        $tree = json_decode(File::get(resource_path("js/i18n/{$locale}.json")), true, 512, JSON_THROW_ON_ERROR);

        return $this->flatten($tree);
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<int, string>
     */
    private function flatten(array $tree, string $prefix = ''): array
    {
        $keys = [];

        foreach ($tree as $key => $value) {
            $keys = is_array($value)
                ? [...$keys, ...$this->flatten($value, "{$prefix}{$key}.")]
                : [...$keys, "{$prefix}{$key}"];
        }

        return $keys;
    }

    /**
     * @return array<int, SplFileInfo>
     */
    private function featureFiles(): array
    {
        $directory = app_path('Features');

        if (! is_dir($directory)) {
            return [];
        }

        return array_values(array_filter(
            File::allFiles($directory),
            fn (SplFileInfo $file) => $file->getExtension() === 'php'
                && (str_contains($file->getPathname(), '/Queries/') || str_contains($file->getPathname(), '/Mutations/')),
        ));
    }

    private function classFromFile(SplFileInfo $file): ?string
    {
        $relative = substr($file->getPathname(), strlen(app_path('Features')) + 1, -4);

        return $relative === '' ? null : 'App\\Features\\'.str_replace('/', '\\', $relative);
    }

    private function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }

    private function short(Throwable $e): string
    {
        return str_replace("\n", ' ', mb_substr($e->getMessage(), 0, 120));
    }

    private function pass(string $name, string $message): void
    {
        $this->results[] = [self::OK, $name, $message];
    }

    private function flag(string $name, string $message): void
    {
        $this->results[] = [self::WARN, $name, $message];
    }

    private function broke(string $name, string $message): void
    {
        $this->results[] = [self::FAIL, $name, $message];
    }

    private function count(string $status): int
    {
        return count(array_filter($this->results, fn (array $result) => $result[0] === $status));
    }
}
