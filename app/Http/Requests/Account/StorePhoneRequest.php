<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

/**
 * E.164 and nothing else: a leading plus, a country code that does not start
 * at zero, and up to fifteen digits in total. Every gateway wants this shape,
 * and normalising a local format would mean guessing a country the app was
 * never told.
 */
class StorePhoneRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone)) {
            // Spaces, dashes and brackets are how people write a number down.
            $this->merge(['phone' => preg_replace('/[\s\-().]/', '', $phone)]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => __('notifications.phone.invalid')];
    }
}
