<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Enums\NotificationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole matrix, or the part of it the screen changed: a map of
 * notification key to the channels that should be on.
 *
 * Types and channels are checked for shape here and for existence in the
 * action, which drops what it does not know rather than failing the save.
 */
class UpdateNotificationSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'preferences' => ['required', 'array', 'max:200'],
            'preferences.*' => ['array', 'max:8'],
            'preferences.*.*' => ['string', Rule::in(array_column(NotificationChannel::cases(), 'value'))],
        ];
    }
}
