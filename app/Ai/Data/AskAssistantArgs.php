<?php

declare(strict_types=1);

namespace App\Ai\Data;

use App\Rules\BoundedString;
use Spatie\LaravelData\Attributes\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What `assistant.ask` takes. Validated into this class before authorize()
 * and the prompt see it, so an oversized question is a 422 and not a bill.
 */
#[TypeScript]
final class AskAssistantArgs extends Data
{
    public function __construct(
        // BoundedString rather than `required|string|max:`: Laravel skips
        // non-implicit rules on a blank string, so "" would slip through.
        #[Rule(new BoundedString(1, 4000))]
        public string $question,
    ) {}
}
