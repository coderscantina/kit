<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Users\CreateUser;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserSocialLink;
use App\Services\Account\SecurityLog;
use App\Services\Auth\TwoFactorAuthService;
use App\Support\FeatureGate;
use App\Support\SocialProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

/**
 * Sign in with an identity provider, and link one to an account that already
 * exists.
 *
 * These are browser redirects, not XHR: the provider hands control back to a
 * URL, so every outcome ends in a redirect the SPA reads off the query string.
 * Failures never say which of the checks below refused, because the difference
 * between "no such account" and "that account exists but is unverified" is
 * exactly what an attacker probing addresses wants to learn.
 *
 * @see docs/social-login.md
 */
class SocialLoginController extends Controller
{
    private const string PENDING_2FA_USER_ID = 'auth.social.pending_2fa_user_id';

    private const string RETURN_TO = 'auth.social.return_to';

    private const string LINK_RETURN_TO = 'auth.social.link_return_to';

    public function __construct(
        private readonly CreateUser $createUser,
        private readonly TwoFactorAuthService $twoFactor,
        private readonly SecurityLog $securityLog,
    ) {}

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(SocialProviders::supports($provider), 404);

        $request->session()->put(self::RETURN_TO, $this->safePath($request->query('return'), '/'));

        return $this->driver($provider, 'auth.social.callback')->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(SocialProviders::supports($provider), 404);

        try {
            $socialUser = $this->driver($provider, 'auth.social.callback')->user();
            $user = DB::transaction(fn (): User => $this->resolveUser($provider, $socialUser));

            // The second factor is the point of the second factor: a provider
            // that vouches for the address does not stand in for it.
            if ($user->hasEnabledTwoFactor()) {
                $request->session()->put(self::PENDING_2FA_USER_ID, $user->id);

                return redirect()->to('/login?social_2fa=1');
            }

            return redirect()->to($this->signIn($request, $user));
        } catch (ValidationException $exception) {
            Log::info('Social sign-in refused', ['provider' => $provider, 'errors' => $exception->errors()]);
        } catch (Throwable $exception) {
            Log::warning('Social sign-in failed', ['provider' => $provider, 'error' => $exception->getMessage()]);
        }

        return redirect()->to('/login?social_error=1&provider='.urlencode($provider));
    }

    /**
     * The second half of a sign-in that stopped at the second factor. The
     * session holds the user id and nothing else, so a stolen redirect is not
     * a session.
     */
    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $userId = $request->session()->get(self::PENDING_2FA_USER_ID);

        if (! is_string($userId) || $userId === '') {
            return response()->json([
                'message' => __('auth.social_session_expired'),
                'error_code' => 'SOCIAL_SESSION_EXPIRED',
            ], 401);
        }

        $user = User::query()->findOrFail($userId);

        if (! $this->twoFactor->verifyForUser($user, $request->string('code')->toString())) {
            return response()->json([
                'message' => __('auth.invalid_totp_code'),
                'error_code' => 'INVALID_TOTP_CODE',
            ], 403);
        }

        $request->session()->forget(self::PENDING_2FA_USER_ID);

        return response()->json(['message' => __('auth.login_successful'), 'redirect' => $this->signIn($request, $user)]);
    }

    public function linkRedirect(Request $request, string $provider): RedirectResponse
    {
        abort_unless(SocialProviders::supports($provider), 404);

        $request->session()->put(
            self::LINK_RETURN_TO,
            $this->safePath($request->query('return'), '/account/security'),
        );

        return $this->driver($provider, 'auth.social.link.callback')->redirect();
    }

    public function linkCallback(Request $request, string $provider): RedirectResponse
    {
        abort_unless(SocialProviders::supports($provider), 404);

        $return = $this->safePath($request->session()->pull(self::LINK_RETURN_TO), '/account/security');
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        try {
            $socialUser = $this->driver($provider, 'auth.social.link.callback')->user();
            $externalId = (string) $socialUser->getId();

            $existing = UserSocialLink::query()
                ->where('provider', $provider)
                ->where('external_id', $externalId)
                ->first();

            if ($existing && $existing->user_id !== $user->id) {
                return redirect()->to($this->withQuery($return, ['social' => 'conflict', 'provider' => $provider]));
            }

            if (! $existing) {
                $this->link($user, $provider, $socialUser);
                $this->securityLog->record($user, SecurityEvent::SOCIAL_LINKED, ['provider' => $provider]);
            }

            return redirect()->to($this->withQuery($return, ['social' => 'linked', 'provider' => $provider]));
        } catch (Throwable $exception) {
            Log::warning('Social link failed', [
                'provider' => $provider,
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return redirect()->to($this->withQuery($return, ['social' => 'error', 'provider' => $provider]));
    }

    /**
     * Which local account this provider identity belongs to, creating one if
     * the installation still accepts registrations.
     */
    private function resolveUser(string $provider, SocialiteUser $socialUser): User
    {
        $externalId = (string) $socialUser->getId();
        $email = Str::lower(trim((string) ($socialUser->getEmail() ?? '')));

        if ($email === '') {
            throw ValidationException::withMessages(['email' => __('auth.social_email_missing')]);
        }

        $link = UserSocialLink::query()
            ->where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();

        if ($link) {
            $link->last_used_at = now();
            $link->save();

            return $link->user;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            // Adopting an existing account on a bare email match is a
            // pre-registration hijack: an attacker registers under the
            // victim's address and waits for them to arrive through the
            // provider, landing them in an account the attacker still holds
            // the password to. Only an address both sides have verified is
            // proof of ownership; anything else has to be linked from a
            // session that already proved it owns the account.
            if (! $user->hasVerifiedEmail() || ! $this->providerVerifiedEmail($provider, $socialUser)) {
                throw ValidationException::withMessages(['email' => __('auth.social_link_required')]);
            }
        } else {
            $user = $this->register($socialUser, $email);
        }

        $this->link($user, $provider, $socialUser);

        return $user;
    }

    private function register(SocialiteUser $socialUser, string $email): User
    {
        // Same gate as RegisterController: once an installation has its first
        // account, only invitees may create new ones. Without this the
        // provider redirect is an open registration endpoint for anyone
        // holding an account with the configured provider.
        if (! FeatureGate::registrationOpen()) {
            throw ValidationException::withMessages(['email' => __('auth.registration_closed')]);
        }

        return $this->createUser->execute([
            'name' => trim((string) $socialUser->getName()) ?: ($socialUser->getNickname() ?? $email),
            'email' => $email,
            // Unknown to anyone, including the account owner: signing in with
            // a password means going through the reset flow first.
            'password' => Hash::make(Str::random(48)),
            'locale' => app()->getLocale(),
            'email_verified_at' => now(),
        ]);
    }

    private function link(User $user, string $provider, SocialiteUser $socialUser): UserSocialLink
    {
        return UserSocialLink::query()->create([
            'user_id' => $user->id,
            'provider' => $provider,
            'external_id' => (string) $socialUser->getId(),
            'nickname' => $socialUser->getNickname(),
            'email' => $socialUser->getEmail(),
            'last_used_at' => now(),
        ]);
    }

    /**
     * Whether the provider states that it verified the address itself.
     *
     * OIDC providers report this as `email_verified`. Socialite's GitHub
     * driver only ever returns the primary address GitHub reports as
     * verified, so there the guarantee comes from the driver rather than the
     * payload.
     */
    private function providerVerifiedEmail(string $provider, SocialiteUser $socialUser): bool
    {
        if ($provider === 'github') {
            return true;
        }

        $raw = method_exists($socialUser, 'getRaw') ? $socialUser->getRaw() : [];

        return (bool) (Arr::get($raw, 'email_verified') ?? Arr::get($raw, 'verified_email') ?? false);
    }

    private function signIn(Request $request, User $user): string
    {
        Auth::guard('web')->login($user);

        $user->last_login_at = now();
        $user->save();

        $this->securityLog->record($user, SecurityEvent::SIGNED_IN);
        $request->session()->regenerate();

        return $this->safePath($request->session()->pull(self::RETURN_TO), '/');
    }

    private function driver(string $provider, string $route): AbstractProvider
    {
        $driver = Socialite::driver($provider);

        // Every OAuth2 driver is an AbstractProvider; the contract Socialite
        // returns is narrower than the redirect-url and scope calls below.
        if (! $driver instanceof AbstractProvider) {
            throw new RuntimeException("Provider [{$provider}] does not support a custom redirect URL.");
        }

        $driver->redirectUrl(route($route, ['provider' => $provider]));
        $scopes = SocialProviders::scopes($provider);

        return $scopes === [] ? $driver : $driver->scopes($scopes);
    }

    /**
     * Same rule as the client's safe-return-path: a path on this origin, and
     * never a protocol-relative URL that would leave it.
     */
    private function safePath(mixed $path, string $fallback): string
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }

        return $path;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function withQuery(string $path, array $query): string
    {
        return $path.(str_contains($path, '?') ? '&' : '?').http_build_query($query);
    }
}
