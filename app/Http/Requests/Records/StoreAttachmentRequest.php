<?php

declare(strict_types=1);

namespace App\Http\Requests\Records;

use App\Support\Records;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * `mimes` reads the type from the bytes and `extensions` checks the name,
     * so a renamed executable fails one or the other. SVG is not on the list:
     * it is a document that can carry script, not a picture.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var array<int, string> $extensions */
        $extensions = config('kit.attachments.extensions');
        $allowed = implode(',', $extensions);

        return [
            'type' => ['required', 'string', 'regex:'.Records::TYPE_PATTERN],
            'id' => ['required', 'ulid'],
            'file' => [
                'required',
                'file',
                "mimes:{$allowed}",
                "extensions:{$allowed}",
                'max:'.(int) config('kit.attachments.max_kilobytes'),
            ],
        ];
    }
}
