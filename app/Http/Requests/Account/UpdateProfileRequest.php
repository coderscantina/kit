<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Rules\BoundedString;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', new BoundedString(1, 128)],
            'locale' => ['sometimes', 'string', 'in:en,de'],
        ];
    }
}
