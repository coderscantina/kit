<?php

declare(strict_types=1);

namespace Tests\Fixtures\Records;

use App\Models\User;

final class WidgetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Widget $widget): bool
    {
        return true;
    }

    public function update(User $user, Widget $widget): bool
    {
        return $widget->owner_id === $user->id;
    }
}
