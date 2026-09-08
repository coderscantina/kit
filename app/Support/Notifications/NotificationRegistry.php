<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Notifications\Attributes\NotificationType;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use RuntimeException;

/**
 * Every notification a user can configure, found by scanning
 * app/Notifications for the #[NotificationType] attribute.
 *
 * Nothing registers itself. Writing the class is the registration, which is
 * also why the preferences screen can never drift from what the app can
 * actually send: both read this list.
 *
 * Resolved once per process. The set only changes when a file does, so a
 * long-lived Octane worker or queue worker holding it is holding the truth
 * until the next deploy restarts it.
 */
final class NotificationRegistry
{
    /** @var array<string, NotificationTypeDefinition>|null */
    private ?array $types = null;

    /**
     * @param  string  $path  directory to scan
     * @param  string  $namespace  namespace that directory maps to
     */
    public function __construct(
        private readonly string $path,
        private readonly string $namespace,
    ) {}

    /**
     * @return array<string, NotificationTypeDefinition> keyed by the stable key
     */
    public function all(): array
    {
        return $this->types ??= $this->scan();
    }

    public function find(string $key): ?NotificationTypeDefinition
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @param  class-string  $class
     */
    public function forClass(string $class): ?NotificationTypeDefinition
    {
        foreach ($this->all() as $definition) {
            if ($definition->class === $class) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * The types grouped the way the preferences screen lays them out, in
     * discovery order within each group.
     *
     * @return array<string, array<int, NotificationTypeDefinition>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->all() as $definition) {
            $groups[$definition->group][] = $definition;
        }

        return $groups;
    }

    /**
     * @return array<string, NotificationTypeDefinition>
     */
    private function scan(): array
    {
        if (! is_dir($this->path)) {
            return [];
        }

        $types = [];

        foreach (File::allFiles($this->path) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(rtrim($this->path, '/')) + 1);

            /** @var class-string $class */
            $class = rtrim($this->namespace, '\\').'\\'.str_replace('/', '\\', substr($relative, 0, -4));

            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);

            if ($reflection->isAbstract()) {
                continue;
            }

            foreach ($reflection->getAttributes(NotificationType::class) as $attribute) {
                $definition = NotificationTypeDefinition::fromAttribute($class, $attribute->newInstance());

                if (isset($types[$definition->key])) {
                    throw new RuntimeException(
                        "Notification key '{$definition->key}' is declared twice: {$types[$definition->key]->class} and {$class}"
                    );
                }

                $types[$definition->key] = $definition;
            }
        }

        return $types;
    }
}
