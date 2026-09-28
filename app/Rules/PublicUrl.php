<?php

declare(strict_types=1);

namespace App\Rules;

use App\Exceptions\UnsafeUrlException;
use App\Services\Security\OutboundUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A URL the server may post to: http or https, resolving to public addresses
 * only. Checked when the URL is saved so the user hears about it then; the
 * sender checks again before every request, because DNS can change.
 */
class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(OutboundUrlGuard::class)->assertSafe((string) $value);
        } catch (UnsafeUrlException) {
            $fail('validation.public_url')->translate();
        }
    }
}
