<?php

declare(strict_types=1);

namespace App\Http\Requests\SavedViews;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavedViewRequest extends FormRequest
{
    /**
     * `params` is the list's own query bag, so its keys are not knowable
     * here. It is bounded instead: a fixed number of short string values,
     * which is what a URL can carry anyway.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'scope' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9._-]*$/'],
            'name' => ['required', 'string', 'max:80'],
            'params' => ['required', 'array', 'max:20'],
            'params.*' => ['nullable', 'string', 'max:200'],
            'isDefault' => ['boolean'],
        ];
    }
}
