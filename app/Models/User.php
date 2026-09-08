<?php

declare(strict_types=1);

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use CodersCantina\Filter\Filterable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Kit\Reactive\Invalidation\HasReactiveInvalidation;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string $locale
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property string|null $avatar_path
 * @property string|null $role_id
 * @property bool $is_root
 * @property string|null $two_factor_secret
 * @property array<int, string>|null $two_factor_backup_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $last_login_at
 * @property string|null $remember_token
 */
#[Fillable(['name', 'email', 'password', 'locale', 'role_id'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_backup_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    use Filterable;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPushSubscriptions;
    use HasReactiveInvalidation;
    use HasUlids;
    use Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'locale' => 'en',
        'is_root' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_root' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_backup_codes' => 'encrypted:array',
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * The address change waiting for the link mailed to it to be opened.
     *
     * @return HasOne<EmailChange, $this>
     */
    public function emailChange(): HasOne
    {
        return $this->hasOne(EmailChange::class);
    }

    /**
     * @return HasMany<UserSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * The named list states this user has saved.
     *
     * @return HasMany<SavedView, $this>
     */
    public function savedViews(): HasMany
    {
        return $this->hasMany(SavedView::class);
    }

    /**
     * The identity providers this account can sign in with.
     *
     * @return HasMany<UserSocialLink, $this>
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(UserSocialLink::class);
    }

    /**
     * The inbox. Overridden so rows are the app's Notification model, which
     * carries reactive invalidation and the escalation columns; the
     * framework's own model has neither, and a delivery through it would
     * write a row no subscription ever hears about.
     *
     * @return MorphMany<Notification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    /**
     * What this account chose to receive, and where. A type with no row here
     * has not been chosen for and takes the type's own defaults.
     *
     * @return HasMany<NotificationPreference, $this>
     */
    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    /**
     * The number waiting on the code texted to it, if one was requested.
     *
     * @return HasOne<PhoneVerification, $this>
     */
    public function phoneVerification(): HasOne
    {
        return $this->hasOne(PhoneVerification::class);
    }

    /**
     * @return HasMany<SecurityEvent, $this>
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null && $this->phone_verified_at !== null;
    }

    /**
     * Where the SMS channel sends. An unverified number is not a route: the
     * whole reason for verifying is that nobody can point this app's texts at
     * a stranger's handset.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->hasVerifiedPhone() ? $this->phone : null;
    }

    public function hasEnabledTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    public function hasRole(string $key): bool
    {
        return $this->role?->key === $key;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
