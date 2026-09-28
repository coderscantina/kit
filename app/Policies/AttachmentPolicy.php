<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * An attachment has no rules of its own: it defers to the record it hangs
 * off. Whoever may view the record may open its files, whoever may update
 * it may remove one.
 */
class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        return Gate::forUser($user)->allows('view', $attachment->attachable);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return Gate::forUser($user)->allows('update', $attachment->attachable);
    }
}
