<?php

declare(strict_types=1);

namespace App\Actions\Invites;

use App\Actions\Users\CreateUser;
use App\Models\Invite;
use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Accepts an invite either for an existing account with the invited address
 * or by creating one. Either way the account ends up with the invited role.
 */
class AcceptInvite
{
    public function __construct(
        private readonly CreateUser $createUser,
        private readonly AuthorizationService $authorization,
    ) {}

    /**
     * @param  array{name: string, password: string}|null  $registration
     */
    public function execute(Invite $invite, string $token, ?User $existing, ?array $registration): User
    {
        if (! $invite->isPending() || ! $invite->tokenMatches($token)) {
            throw new AuthorizationException(__('auth.invite_invalid'));
        }

        return DB::transaction(function () use ($invite, $existing, $registration) {
            $locked = Invite::query()->lockForUpdate()->findOrFail($invite->id);

            if (! $locked->isPending()) {
                throw new AuthorizationException(__('auth.invite_invalid'));
            }

            if ($existing !== null) {
                if (strcasecmp($existing->email, $locked->email) !== 0) {
                    throw new AuthorizationException(__('auth.invite_email_mismatch'));
                }

                $user = $existing;
                $user->role_id ??= $locked->role_id;
                $user->save();
                $this->authorization->invalidateUser($user);
            } else {
                if ($registration === null) {
                    throw new AuthorizationException(__('auth.invite_requires_account'));
                }

                $user = $this->createUser->execute([
                    'name' => $registration['name'],
                    'email' => $locked->email,
                    'password' => $registration['password'],
                    // The invite went to this address, which is verification enough.
                    'email_verified_at' => now(),
                ], $locked->role);
            }

            $locked->accepted_at = now();
            $locked->save();

            return $user;
        });
    }
}
