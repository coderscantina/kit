<?php

declare(strict_types=1);

namespace Tests\Fixtures\Records;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * A record with history and files, for the tests that need one. Aliased to
 * `App\Models\Widget` so the `widgets` type resolves the way a generated
 * feature's would. Anyone may view it, only its owner may change it.
 *
 * @property string $id
 * @property string $owner_id
 * @property string $name
 */
#[Fillable(['owner_id', 'name'])]
class Widget extends Model
{
    use Auditable;
    use HasAttachments;

    public static function install(): void
    {
        if (! class_exists('App\\Models\\Widget', false)) {
            class_alias(self::class, 'App\\Models\\Widget');
        }

        Schema::create('widgets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('owner_id');
            $table->string('name');
            $table->timestamps();
        });

        Gate::policy(self::class, WidgetPolicy::class);
    }
}
