<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Data\SocialLinkData;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSocialLink;
use App\Services\Account\SecurityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The providers connected to the signed-in account. Connecting one is a
 * browser redirect and lives in SocialLoginController; this is the list and
 * the disconnect.
 */
class SocialLinkController extends Controller
{
    public function __construct(
        private readonly SecurityLog $securityLog,
    ) {}

    /**
     * @return array<int, SocialLinkData>
     */
    public function index(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return $user->socialLinks()
            ->orderBy('provider')
            ->get()
            ->map(SocialLinkData::fromModel(...))
            ->all();
    }

    /**
     * Disconnecting the last provider is allowed: every account has a
     * password, and an account created through a provider reaches its
     * password through the reset flow. Refusing here would strand anyone who
     * loses access to the provider instead.
     */
    public function destroy(Request $request, string $provider): Response
    {
        /** @var User $user */
        $user = $request->user();

        $link = $user->socialLinks()->where('provider', $provider)->first();

        abort_unless($link instanceof UserSocialLink, 404);

        $link->delete();

        $this->securityLog->record($user, SecurityEvent::SOCIAL_UNLINKED, ['provider' => $provider]);

        return response()->noContent();
    }
}
