<?php

declare(strict_types=1);

namespace App\Http\Requests\SavedViews;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSavedViewRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'params' => ['sometimes', 'array', 'max:20'],
            'params.*' => ['nullable', 'string', 'max:200'],
            'isDefault' => ['sometimes', 'boolean'],
        ];
    }
}
