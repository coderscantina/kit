<?php

declare(strict_types=1);

namespace App\Data;

use App\Models\UserSocialLink;
use App\Support\SocialProviders;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A provider connected to the account, as the security page shows it. The
 * label and icon come from config/social.php rather than the client, so
 * adding a provider needs no frontend change.
 */
#[TypeScript]
class SocialLinkData extends Data
{
    public function __construct(
        public string $provider,
        public string $label,
        public string $icon,
        public ?string $nickname,
        public ?string $email,
        public ?string $lastUsedAt,
        public string $connectedAt,
    ) {}

    public static function fromModel(UserSocialLink $link): self
    {
        $provider = SocialProviders::enabled()[$link->provider] ?? null;

        return new self(
            provider: $link->provider,
            label: $provider['label'] ?? ucfirst($link->provider),
            icon: $provider['icon'] ?? 'lucide:key-round',
            nickname: $link->nickname,
            email: $link->email,
            lastUsedAt: $link->last_used_at?->toIso8601String(),
            connectedAt: $link->created_at?->toIso8601String() ?? '',
        );
    }
}
