<?php

declare(strict_types=1);

namespace App\Http\Requests\Invites;

use Illuminate\Foundation\Http\FormRequest;

class StoreInviteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'role' => ['required', 'string', 'exists:roles,key'],
        ];
    }
}
