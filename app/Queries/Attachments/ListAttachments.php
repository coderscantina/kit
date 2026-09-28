<?php

declare(strict_types=1);

namespace App\Queries\Attachments;

use App\Data\AttachmentData;
use App\Data\RecordArgs;
use App\Models\Attachment;
use App\Models\Concerns\HasAttachments;
use App\Support\Records;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Kit\Reactive\Attributes\ReactiveQuery;
use Kit\Reactive\Dep;
use Kit\Reactive\Query;
use Spatie\LaravelData\Data;

/**
 * The files on one record, newest first. An upload, a delete or a finished
 * preview on another screen shows up here without a reload.
 *
 * @extends Query<RecordArgs>
 */
#[ReactiveQuery('attachments.list', result: AttachmentData::class, list: true)]
final class ListAttachments extends Query
{
    public static function args(): string
    {
        return RecordArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        Records::authorize($this->gate($user), 'view', $args->type, $args->id, HasAttachments::class);
    }

    /**
     * @return array<int, Dep>
     */
    public function reads(Data $args): array
    {
        return [Dep::eq('attachments', 'attachable_id', $args->id)];
    }

    /**
     * @return Collection<int, AttachmentData>
     */
    public function handle(Data $args): mixed
    {
        $class = Records::modelClass($args->type, HasAttachments::class);

        if ($class === null) {
            return collect();
        }

        $attachments = Attachment::query()
            ->with('uploader')
            ->where('attachable_type', (new $class)->getMorphClass())
            ->where('attachable_id', $args->id)
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return AttachmentData::collect($attachments);
    }
}
