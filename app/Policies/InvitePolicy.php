<?php

declare(strict_types=1);

namespace App\Policies;

class InvitePolicy extends ResourcePolicy
{
    protected string $resource = 'invites';
}
