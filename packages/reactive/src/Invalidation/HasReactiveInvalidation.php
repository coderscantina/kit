<?php

declare(strict_types=1);

namespace Kit\Reactive\Invalidation;

use Illuminate\Database\Eloquent\Model;

/**
 * Records every saved/deleted/restored row into the ChangeBuffer so the
 * subscriptions depending on it are recomputed after commit.
 *
 * Bulk writes: wrap them in `Model::withoutReactiveEvents()` and follow up
 * with one `Invalidate::table('name')`. Never `Model::withoutEvents()`,
 * which also silences the ULID and any audit hooks.
 *
 * @phpstan-ignore trait.unused
 */
trait HasReactiveInvalidation
{
    /** @var array<class-string, true> */
    protected static array $reactiveEventsMuted = [];

    public static function bootHasReactiveInvalidation(): void
    {
        static::saved(fn (Model $model) => static::recordReactiveChange($model, deleted: false));
        static::deleted(fn (Model $model) => static::recordReactiveChange($model, deleted: true));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $model) => static::recordReactiveChange($model, deleted: false));
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutReactiveEvents(callable $callback): mixed
    {
        $had = isset(static::$reactiveEventsMuted[static::class]);
        static::$reactiveEventsMuted[static::class] = true;

        try {
            return $callback();
        } finally {
            if (! $had) {
                unset(static::$reactiveEventsMuted[static::class]);
            }
        }
    }

    public static function reactiveEventsMuted(): bool
    {
        return isset(static::$reactiveEventsMuted[static::class]);
    }

    protected static function recordReactiveChange(Model $model, bool $deleted): void
    {
        if (static::reactiveEventsMuted()) {
            return;
        }

        app(ChangeBuffer::class)->record(Change::fromModel($model, $deleted));
    }
}
