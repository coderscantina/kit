<?php

declare(strict_types=1);

namespace Tests\Fixtures\Reactive;

use App\Models\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Kit\Reactive\Concurrency\Versioned;

/**
 * @property string $id
 * @property string $owner_id
 * @property string $title
 * @property string $body
 * @property int $version
 */
#[Fillable(['owner_id', 'title', 'body'])]
final class Note extends Model
{
    use Versioned;

    protected $table = 'notes';

    /**
     * Idempotent: on MySQL a DDL statement commits the transaction
     * RefreshDatabase holds, so the table is created once, before it.
     */
    public static function migrate(): void
    {
        if (Schema::hasTable('notes')) {
            return;
        }

        Schema::create('notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('owner_id');
            $table->string('title');
            $table->string('body', 4000)->default('');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
    }
}
