<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Names a record the way the client does: its table (`posts`) and its id.
 * The table resolves to `App\Models\Post` by the same rule the generators
 * name files by, so nothing registers a model to get history or
 * attachments. The trait is the opt-in: a model without it is not a record
 * anyone can address this way, whatever the client sends.
 */
final class Records
{
    public const string TYPE_PATTERN = '/^[a-z][a-z0-9_]*$/';

    /**
     * @param  class-string  $concern  the trait the model has to use
     * @return class-string<Model>|null
     */
    public static function modelClass(string $type, string $concern): ?string
    {
        if (preg_match(self::TYPE_PATTERN, $type) !== 1) {
            return null;
        }

        $class = 'App\\Models\\'.Str::studly(Str::singular($type));

        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return null;
        }

        return in_array($concern, class_uses_recursive($class), true) ? $class : null;
    }

    /**
     * Every type whose model uses the trait, by reading `app/Models`. Cheap
     * enough for a settings page; not for a request path.
     *
     * @param  class-string  $concern
     * @return array<string, class-string<Model>> type => model class
     */
    public static function types(string $concern): array
    {
        $types = [];

        foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (is_subclass_of($class, Model::class) && in_array($concern, class_uses_recursive($class), true)) {
                $types[(new $class)->getTable()] = $class;
            }
        }

        ksort($types);

        return $types;
    }

    /**
     * The record's own policy decides: `view` to read its history or files,
     * `update` to attach one. A record that is gone falls back to `viewAny`
     * for reading, so a deleted row's history stays reachable for whoever
     * could list the rows; nothing can be attached to it.
     *
     * @param  class-string  $concern
     *
     * @throws AuthorizationException
     */
    public static function authorize(Gate $gate, string $ability, string $type, string $id, string $concern): ?Model
    {
        $class = self::modelClass($type, $concern) ?? throw new AuthorizationException;
        $record = $class::query()->find($id);

        match (true) {
            $record !== null => $gate->authorize($ability, $record),
            $ability === 'view' => $gate->authorize('viewAny', $class),
            default => throw new AuthorizationException,
        };

        return $record;
    }

    /** The name the client and the webhook events use for this model. */
    public static function typeOf(Model $model): string
    {
        return $model->getTable();
    }
}
