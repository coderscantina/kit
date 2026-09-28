<?php

declare(strict_types=1);

namespace App\Data;

use App\Rules\PublicUrl;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * An endpoint's settings, for create (no id) and update. An event pattern
 * is `*`, `posts.*` or `posts.updated`; `webhooks.events` has the concrete ones.
 */
#[TypeScript]
final class SaveWebhookArgs extends Data
{
    /**
     * @param  array<int, string>  $events
     */
    public function __construct(
        public string $url,
        public array $events,
        public ?string $description = null,
        public bool $active = true,
        public ?string $id = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'id' => ['nullable', 'ulid'],
            'url' => ['required', 'string', 'max:2048', 'url:http,https', new PublicUrl],
            'events' => ['required', 'array', 'min:1', 'max:50'],
            'events.*' => ['string', 'distinct', 'regex:/^(\*|[a-z][a-z0-9_]*\.(\*|created|updated|deleted|restored))$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
