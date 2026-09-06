<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RequestEmailChangeRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                // Not `ignore(me)`: asking for the address already on the
                // account is a no-op dressed as a change, and the confirmation
                // mail would be nonsense.
                Rule::unique('users', 'email'),
            ],
        ];
    }
}
