<?php

declare(strict_types=1);

namespace App\Http\Requests\Invites;

use App\Rules\BoundedString;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Logged-in users accept with the token alone; anonymous visitors also
 * supply the name and password for the account the invite creates.
 */
class AcceptInviteRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $registering = $this->user() === null;

        return [
            'token' => ['required', 'string', 'size:48'],
            'name' => $registering ? [new BoundedString(1, 128)] : ['prohibited'],
            'password' => $registering ? ['required', 'confirmed', Password::defaults()] : ['prohibited'],
        ];
    }
}
