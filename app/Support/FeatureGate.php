<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Throwable;

/**
 * The only reader of config/features.php.
 */
final class FeatureGate
{
    public static function realtimeEnabled(): bool
    {
        return self::override('realtime')
            ?? (filled(config('reverb.apps.apps.0.key')) && config('broadcasting.default') === 'reverb');
    }

    /**
     * Whether the SPA should offer AI at all. Derived from the provider key,
     * so a checkout with no key simply has no assistant rather than a button
     * that fails on click.
     */
    public static function aiEnabled(): bool
    {
        return self::override('ai') ?? filled(config('ai.drivers.'.config('ai.driver').'.api_key'));
    }

    /**
     * Whether the browser may be offered push notifications. Derived from the
     * VAPID public key, because a subscription made without one cannot be
     * pushed to and there is nothing to gain from offering the switch.
     */
    public static function pushEnabled(): bool
    {
        return self::override('push') ?? filled(config('webpush.vapid.public_key'));
    }

    /** Derived from whether any listed provider has credentials. */
    public static function socialEnabled(): bool
    {
        return self::override('social') ?? SocialProviders::anyEnabled();
    }

    public static function impersonationEnabled(): bool
    {
        return self::override('impersonation') ?? true;
    }

    /**
     * Whether open self-registration (no invite) is accepted.
     *
     * Open until the first account exists, invite-only afterwards, so a fresh
     * install on a public address cannot be claimed by a stranger later. The
     * answer is latched into a marker file rather than taken from a live
     * query every time, because the query has two ways of wrongly reopening a
     * populated instance: a transient database error, and deleting the last
     * account. APP_ALLOW_REGISTRATION overrides.
     */
    public static function registrationOpen(): bool
    {
        $override = self::override('registration');

        if ($override !== null) {
            return $override;
        }

        $state = app(InstallState::class);

        if ($state->registrationClosed()) {
            return false;
        }

        try {
            $hasAccount = User::query()->exists();
        } catch (Throwable $e) {
            report($e);

            // Unknown. On an installed instance refuse rather than hand out an
            // owner account over a database blip; before setup the database
            // may legitimately not exist yet.
            return ! $state->exists();
        }

        if ($hasAccount) {
            $state->closeRegistration();

            return false;
        }

        return true;
    }

    /**
     * @return array{realtime: bool, registration: bool, impersonation: bool, ai: bool, push: bool, social: bool}
     */
    public static function features(): array
    {
        return [
            'realtime' => self::realtimeEnabled(),
            'registration' => self::registrationOpen(),
            'impersonation' => self::impersonationEnabled(),
            'ai' => self::aiEnabled(),
            'push' => self::pushEnabled(),
            'social' => self::socialEnabled(),
        ];
    }

    private static function override(string $feature): ?bool
    {
        $value = config("features.{$feature}");

        // An empty line in .env reads as '' and must mean "derive", not false.
        return $value === null || $value === '' ? null : filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
