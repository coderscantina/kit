<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Models\User;
use App\Services\Auth\AuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccessTokenRequest extends FormRequest
{
    /** How long a token may live. Null in the payload is "until revoked". */
    public const array LIFETIMES = [30, 90, 365];

    /**
     * A token carries a subset of what its owner may do, never more. The
     * owner's abilities are checked again on every use, so a demoted owner
     * takes their tokens down with them.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:80'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['string', 'distinct', Rule::in(app(AuthorizationService::class)->abilitiesFor($user))],
            'expires_in_days' => ['nullable', 'integer', Rule::in(self::LIFETIMES)],
        ];
    }
}
