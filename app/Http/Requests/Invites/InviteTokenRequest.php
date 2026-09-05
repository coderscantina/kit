<?php

declare(strict_types=1);

namespace App\Http\Requests\Invites;

use Illuminate\Foundation\Http\FormRequest;

class InviteTokenRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'size:48']];
    }
}
