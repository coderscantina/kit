<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The account's phone, in the three states the UI has to tell apart: none,
 * one waiting on its code, and one that is a route.
 */
#[TypeScript]
final class PhoneData extends Data
{
    public function __construct(
        public ?string $phone,
        public bool $verified,
        /** The number a code was texted to, still unconfirmed. */
        public ?string $pendingPhone,
        /** Seconds before another code may be requested. */
        public int $resendIn,
    ) {}

    public static function fromModel(User $user): self
    {
        $pending = $user->phoneVerification()->first();

        return new self(
            phone: $user->phone,
            verified: $user->hasVerifiedPhone(),
            pendingPhone: $pending !== null && $pending->isPending() ? $pending->phone : null,
            resendIn: $pending?->resendCooldown() ?? 0,
        );
    }
}
