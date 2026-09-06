<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class StoreAvatarRequest extends FormRequest
{
    /**
     * The client crops and resizes before uploading, so these are the backstop
     * rather than the everyday path. `image` and the explicit mime list keep
     * SVG out: it is a document that can carry script, not a picture.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $dimension = (int) config('kit.avatars.max_dimension');

        return [
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.(int) config('kit.avatars.max_kilobytes'),
                "dimensions:max_width={$dimension},max_height={$dimension}",
            ],
        ];
    }
}
