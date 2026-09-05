<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

/**
 * The two marker files on the storage volume: the install state that makes
 * kit:setup idempotent, and the registration latch.
 */
class InstallState
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function path(): string
    {
        return (string) config('kit.setup.state_path');
    }

    public function exists(): bool
    {
        return $this->files->exists($this->path());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(): ?array
    {
        if (! $this->exists()) {
            return null;
        }

        $decoded = json_decode($this->files->get($this->path()), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function recordedVersion(): ?string
    {
        $version = $this->read()['app_version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function write(string $version): void
    {
        $state = $this->read() ?? ['installed_at' => now()->toIso8601String()];
        $state['app_version'] = $version;
        $state['upgraded_at'] = now()->toIso8601String();

        $this->putAtomically($this->path(), json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
    }

    /**
     * Whether self-registration has been permanently closed on this install.
     */
    public function registrationClosed(): bool
    {
        return $this->files->exists($this->registrationClosedPath());
    }

    /**
     * Best-effort: a read-only storage directory must not turn a registration
     * attempt into a 500. The caller has already decided to refuse.
     */
    public function closeRegistration(): void
    {
        if ($this->registrationClosed()) {
            return;
        }

        try {
            $this->putAtomically($this->registrationClosedPath(), now()->toIso8601String().PHP_EOL);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function registrationClosedPath(): string
    {
        return (string) config('kit.setup.registration_closed_path');
    }

    /**
     * Write via tmp + rename so a reader never sees a half-written marker.
     */
    private function putAtomically(string $path, string $contents): void
    {
        $directory = dirname($path);

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $tmp = $path.'.tmp.'.getmypid();

        if ($this->files->put($tmp, $contents) === false || ! $this->files->move($tmp, $path)) {
            throw new RuntimeException("Unable to write {$path}");
        }
    }
}
