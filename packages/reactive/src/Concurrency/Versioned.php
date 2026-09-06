<?php

declare(strict_types=1);

namespace Kit\Reactive\Concurrency;

use Illuminate\Database\Eloquent\Model;

/**
 * An integer `version` column the model owns: 1 on create, +1 on every
 * update that changes something. A mutation states the version it read
 * with `$this->lockVersion($model, $args->version)`, and a row that moved
 * in between answers 409 instead of being overwritten.
 *
 * The column is `$table->unsignedInteger('version')->default(1)`. Nothing
 * else sets it; a mutation that writes `version` by hand is a bug.
 *
 * @phpstan-ignore trait.unused
 */
trait Versioned
{
    public static function bootVersioned(): void
    {
        static::saving(function (Model $model): void {
            if (! $model->exists) {
                $model->setAttribute('version', max(1, (int) $model->getAttribute('version')));

                return;
            }

            if ($model->isDirty() && ! $model->isDirty('version')) {
                $model->setAttribute('version', (int) $model->getAttribute('version') + 1);
            }
        });
    }

    public function currentVersion(): int
    {
        return (int) $this->getAttribute('version');
    }
}
