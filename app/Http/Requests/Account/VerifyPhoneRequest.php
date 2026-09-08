<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'digits_between:4,10'],
        ];
    }
}
