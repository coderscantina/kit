<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Services\Auth\AuthorizationService;
use App\Services\Auth\MembershipGuard;
use Illuminate\Support\Facades\DB;

class DeleteUser
{
    public function __construct(
        private readonly MembershipGuard $guard,
        private readonly AuthorizationService $authorization,
    ) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            $this->guard->ensureNotLastOwner($locked);

            DB::table('sessions')->where('user_id', $locked->id)->delete();
            $locked->delete();
        });

        $this->authorization->invalidateUser($user);
    }
}
