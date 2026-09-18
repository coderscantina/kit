<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use stdClass;
use Symfony\Component\Process\Process;

/**
 * Shared by the generators: render a stub with `{{ placeholders }}`, write it
 * without clobbering, insert lines at `// kit:<marker>` anchors, and format
 * what was written with the repository's own formatters.
 */
trait WritesStubs
{
    /** @var array<string, true> Files this run created or changed, for formatWritten(). */
    private array $written = [];

    /**
     * Absolute path of a stub in app/Console/Stubs.
     */
    protected function stub(string $name): string
    {
        $path = app_path("Console/Stubs/{$name}.stub");

        if (! is_file($path)) {
            throw new RuntimeException("Stub {$name}.stub is missing.");
        }

        return $path;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function renderStub(string $path, array $replacements): string
    {
        $stub = file_get_contents($path);

        if ($stub === false) {
            throw new RuntimeException("Stub {$path} is missing.");
        }

        foreach ($replacements as $key => $value) {
            $stub = str_replace('{{ '.$key.' }}', $value, $stub);
        }

        return $stub;
    }

    /**
     * @param  array<string, string>  $replacements
     */
    protected function writeStub(string $stub, string $target, array $replacements, bool $force = false, bool $quiet = false): bool
    {
        $files = new Filesystem;

        if ($files->exists($target) && ! $force) {
            if (! $quiet) {
                $this->components->warn('Exists, left alone: '.$this->relative($target));
            }

            return false;
        }

        $files->ensureDirectoryExists(dirname($target));
        $files->put($target, $this->renderStub($stub, $replacements));
        $this->written[$target] = true;
        $this->components->info('Created '.$this->relative($target));

        return true;
    }

    /**
     * Insert `$lines` directly before the `// kit:<marker>` line, keeping the
     * marker's indentation. Nothing is inserted when the same group is
     * already in the marker's own block, so re-running a generator is a
     * no-op; the same group under a different marker (the ability lists, one
     * per role) still goes in.
     *
     * @param  array<int, string>  $lines
     */
    protected function insertAtMarker(string $file, string $marker, array $lines): void
    {
        $files = new Filesystem;
        $contents = $files->get($file);
        $anchor = "// kit:{$marker}";

        if (! str_contains($contents, $anchor)) {
            throw new RuntimeException("Marker {$anchor} not found in ".$this->relative($file));
        }

        preg_match('/^([ \t]*)'.preg_quote($anchor, '/').'/m', $contents, $match);
        $indent = $match[1] ?? '';
        $block = $this->markerBlock($contents, $anchor, $indent)."\n";

        $insert = '';
        foreach ($lines as $line) {
            $insert .= $indent.$line."\n";
        }

        // The whole group, not line by line: a route record's `{` and `},`
        // match everywhere, and skipping those would insert a broken literal.
        if (str_contains($block, $insert)) {
            return;
        }

        $files->put($file, preg_replace('/^([ \t]*)'.preg_quote($anchor, '/').'/m', $insert.'$1'.$anchor, $contents, 1) ?? $contents);
        $this->updated($file);
    }

    /**
     * Add an import after the file's last top-level import, unless it is
     * there already. `use X;` for PHP, `import ... from '...'` for TS; the
     * formatters sort them afterwards.
     */
    protected function addImport(string $file, string $line): void
    {
        $files = new Filesystem;
        $contents = $files->get($file);

        if (str_contains($contents, $line)) {
            return;
        }

        $pattern = str_starts_with($line, 'use ') ? '/^use [^;]+;$/m' : '/^import .+$/m';

        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            throw new RuntimeException('No import to add after in '.$this->relative($file));
        }

        [$last, $offset] = $matches[0][count($matches[0]) - 1];
        $at = $offset + strlen($last);

        $files->put($file, substr($contents, 0, $at)."\n".$line.substr($contents, $at));
        $this->updated($file);
    }

    /**
     * The lines above the marker that belong to the same array literal: walk
     * back until a line is indented less than the marker is.
     */
    private function markerBlock(string $contents, string $anchor, string $indent): string
    {
        $block = [];

        foreach (explode("\n", $contents) as $line) {
            if (str_contains($line, $anchor)) {
                break;
            }

            $current = strlen($line) - strlen(ltrim($line));

            if (trim($line) !== '' && $current < strlen($indent)) {
                $block = [];

                continue;
            }

            $block[] = $line;
        }

        return implode("\n", $block);
    }

    /**
     * Set a dotted key in a JSON translation file, keeping key order and the
     * two-space style the rest of the file uses.
     *
     * Decoded as objects, not associative arrays. `{}` and `[]` both decode to
     * `[]` with assoc on, so an empty message group such as `"mutations": {}`
     * came back out as `"mutations": []` and vue-i18n stopped seeing a group.
     */
    protected function setJsonKey(string $file, string $dotted, string $value): void
    {
        $files = new Filesystem;
        $data = json_decode($files->get($file), false, 512, JSON_THROW_ON_ERROR);

        if (! $data instanceof stdClass) {
            throw new RuntimeException('Expected a JSON object in '.$this->relative($file).'.');
        }

        $node = $data;
        $parts = explode('.', $dotted);

        foreach (array_slice($parts, 0, -1) as $part) {
            if (! isset($node->{$part}) || ! $node->{$part} instanceof stdClass) {
                $node->{$part} = new stdClass;
            }
            $node = $node->{$part};
        }

        $leaf = $parts[count($parts) - 1];

        if (isset($node->{$leaf})) {
            return;
        }

        $node->{$leaf} = $value;

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $json = preg_replace_callback('/^ +/m', fn (array $m) => str_repeat(' ', intdiv(strlen($m[0]), 2)), $json) ?? $json;
        $files->put($file, $json."\n");
        $this->updated($file);
    }

    /**
     * Run pint and oxfmt over what this run wrote, so generated code passes
     * `bin/gate` as written. Stubs are close to formatted already; this
     * settles what field lists and marker inserts vary. Skipped quietly when
     * a formatter is not installed.
     */
    protected function formatWritten(): void
    {
        $written = array_keys($this->written);
        $this->written = [];

        $php = array_values(array_filter($written, fn (string $file): bool => str_ends_with($file, '.php')));
        $frontend = array_values(array_filter($written, fn (string $file): bool => preg_match('/\.(ts|vue|json)$/', $file) === 1));

        foreach ([[base_path('vendor/bin/pint'), $php], [base_path('node_modules/.bin/oxfmt'), $frontend]] as [$binary, $targets]) {
            if ($targets !== [] && is_executable($binary)) {
                (new Process([$binary, ...$targets], base_path()))->setTimeout(120)->run();
            }
        }
    }

    /** Records a patched file, and says so once per file rather than once per insert. */
    private function updated(string $file): void
    {
        if (! isset($this->written[$file])) {
            $this->components->info('Updated '.$this->relative($file));
        }

        $this->written[$file] = true;
    }

    protected function relative(string $path): string
    {
        return ltrim(str_replace(base_path(), '', $path), '/');
    }
}
