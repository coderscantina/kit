<?php

declare(strict_types=1);

namespace Kit\Reactive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'max:128'],
            'args' => ['sometimes', 'array'],
        ];
    }
}
