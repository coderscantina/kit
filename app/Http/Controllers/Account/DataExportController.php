<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\SecurityEventData;
use App\Data\SessionData;
use App\Data\UserData;
use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Account\SecurityLog;
use App\Services\Account\SessionRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the installation holds about the caller, as one JSON download.
 *
 * Synchronous on purpose: an account's own record is a handful of rows, and a
 * queued job with a signed download link would add a storage lifecycle and a
 * second expiry to reason about for no gain. A derived app that grows large
 * per-user tables should move this to a job.
 */
class DataExportController extends Controller
{
    public function __invoke(Request $request, SessionRegistry $registry, SecurityLog $securityLog): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->load(['role', 'emailChange']);

        $currentKey = UserSession::key($request->session()->getId());

        $payload = [
            'exportedAt' => now()->toIso8601String(),
            'account' => UserData::fromModel($user),
            'sessions' => $registry->list($user)
                ->map(fn (UserSession $session): SessionData => SessionData::fromModel($session, $currentKey))
                ->all(),
            'securityActivity' => $user->securityEvents()
                ->orderByDesc('id')
                ->get()
                ->map(fn (SecurityEvent $event): SecurityEventData => SecurityEventData::fromModel($event))
                ->all(),
            'invitationsSent' => Invite::query()
                ->with(['role', 'inviter'])
                ->where('invited_by', $user->id)
                ->orderByDesc('id')
                ->get()
                ->map(fn (Invite $invite): array => [
                    'email' => $invite->email,
                    'role' => $invite->role?->key,
                    'createdAt' => $invite->created_at?->toIso8601String(),
                    'expiresAt' => $invite->expires_at->toIso8601String(),
                    'acceptedAt' => $invite->accepted_at?->toIso8601String(),
                    'declinedAt' => $invite->declined_at?->toIso8601String(),
                ])
                ->all(),
        ];

        $securityLog->record($user, SecurityEvent::DATA_EXPORTED);

        $filename = 'account-export-'.now()->format('Y-m-d').'.json';

        return response()
            ->json($payload, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ->withHeaders(['Content-Disposition' => "attachment; filename=\"{$filename}\""]);
    }
}
