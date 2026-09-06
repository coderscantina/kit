<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Actions\Invites\AcceptInvite;
use App\Actions\Invites\CreateInvite;
use App\Actions\Invites\DeclineInvite;
use App\Actions\Invites\ResendInvite;
use App\Data\InviteData;
use App\Data\PublicInviteData;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invites\AcceptInviteRequest;
use App\Http\Requests\Invites\InviteTokenRequest;
use App\Http\Requests\Invites\StoreInviteRequest;
use App\Models\Invite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class InviteController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public function index(Request $request): array
    {
        $this->authorize('viewAny', Invite::class);

        $invites = Invite::query()
            ->with(['role', 'inviter'])
            // ULIDs sort by creation time with sub-second resolution.
            ->orderByDesc('id')
            ->paginate($this->perPage($request));

        return InviteData::collect($invites)->toArray();
    }

    public function store(StoreInviteRequest $request, CreateInvite $createInvite): JsonResponse
    {
        $this->authorize('create', Invite::class);

        /** @var User $actor */
        $actor = $request->user();
        $role = Role::byKey($request->string('role')->toString());

        $invite = $createInvite->execute($request->string('email')->toString(), $role, $actor);

        return response()->json(InviteData::fromModel($invite->load(['role', 'inviter'])), 201);
    }

    /**
     * Re-mails a pending invitation with a new token and a new expiry. Not a
     * creation, so 200 with the refreshed invitation rather than 201.
     */
    public function resend(Invite $invite, ResendInvite $resendInvite): JsonResponse
    {
        $this->authorize('update', $invite);

        abort_unless($invite->accepted_at === null && $invite->declined_at === null, 422, __('auth.invite_invalid'));

        return response()->json(InviteData::fromModel($resendInvite->execute($invite)->load(['role', 'inviter'])));
    }

    public function destroy(Invite $invite): Response
    {
        $this->authorize('delete', $invite);

        $invite->delete();

        return response()->noContent();
    }

    /**
     * Token-gated: without the token from the mail the invite does not exist.
     */
    public function show(InviteTokenRequest $request, Invite $invite): PublicInviteData
    {
        abort_unless($invite->isPending() && $invite->tokenMatches($request->string('token')->toString()), 404);

        $invite->load(['role', 'inviter']);

        return PublicInviteData::fromModel($invite, User::query()->where('email', $invite->email)->exists());
    }

    public function accept(AcceptInviteRequest $request, Invite $invite, AcceptInvite $acceptInvite): JsonResponse
    {
        $registration = $request->user() === null
            ? ['name' => $request->string('name')->toString(), 'password' => $request->string('password')->toString()]
            : null;

        $user = $acceptInvite->execute($invite, $request->string('token')->toString(), $request->user(), $registration);

        if ($request->user() === null) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        return response()->json(['user' => UserData::fromModel($user->load('role'))]);
    }

    public function decline(InviteTokenRequest $request, Invite $invite, DeclineInvite $declineInvite): Response
    {
        $declineInvite->execute($invite, $request->string('token')->toString());

        return response()->noContent();
    }
}
