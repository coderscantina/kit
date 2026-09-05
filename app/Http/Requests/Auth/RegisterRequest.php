<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Rules\BoundedString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [new BoundedString(1, 128)],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'locale' => ['sometimes', 'string', 'in:en,de'],
        ];
    }
}
