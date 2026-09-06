<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Account\ConfirmEmailChange;
use App\Actions\Account\RequestEmailChange;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\ConfirmEmailChangeRequest;
use App\Http\Requests\Account\RequestEmailChangeRequest;
use App\Models\EmailChange;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Changing the address is a two-step flow, not a PATCH: the address on the
 * account is what a password reset is mailed to, so it does not move until the
 * new inbox has proved it can receive.
 */
class EmailChangeController extends Controller
{
    public function store(RequestEmailChangeRequest $request, RequestEmailChange $requestChange): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $requestChange->execute($user, $request->string('email')->toString());

        return response()->json([
            'message' => __('auth.email_change_requested'),
            'user' => UserData::fromModel($user->load(['role', 'emailChange'])),
        ]);
    }

    /** Cancel a pending change. Harmless, so no step-up. */
    public function destroy(Request $request): UserData
    {
        /** @var User $user */
        $user = $request->user();

        EmailChange::query()->where('user_id', $user->id)->delete();

        return UserData::fromModel($user->load(['role', 'emailChange']));
    }

    /**
     * Token-gated and reachable without a session: the confirmation link is
     * often opened in whichever browser has the mailbox open.
     */
    public function confirm(ConfirmEmailChangeRequest $request, EmailChange $change, ConfirmEmailChange $confirm): Response
    {
        $confirm->execute($change, $request->string('token')->toString());

        return response()->noContent();
    }
}
