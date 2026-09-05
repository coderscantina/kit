<?php

declare(strict_types=1);

namespace Kit\Reactive\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnsubscribeRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['subscriptionId' => ['required', 'string', 'max:64']];
    }
}
