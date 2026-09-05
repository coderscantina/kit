<?php

declare(strict_types=1);

namespace App\Ai\Actions;

use App\Ai\Data\AskAssistantArgs;
use App\Services\Ai\AiAction;
use App\Services\Ai\Attributes\AiStream;
use App\Services\Ai\Support\Prompt;
use Illuminate\Contracts\Auth\Authenticatable;
use Spatie\LaravelData\Data;

/**
 * The worked example: a plain question answered in prose, streamed.
 *
 * It is here to be read and then replaced. A real action belongs to a
 * feature (`app/Features/Post/Ai/SummarizePost.php`), pins its own model
 * when the default is wrong for the job, and hands the model tools when it
 * needs to look something up.
 *
 * @extends AiAction<AskAssistantArgs>
 */
#[AiStream('assistant.ask')]
final class AskAssistant extends AiAction
{
    public static function args(): string
    {
        return AskAssistantArgs::class;
    }

    public function authorize(Authenticatable $user, Data $args): void
    {
        $this->allow($user, 'ai.use');
    }

    /**
     * No per-request data in here: the system message is sent byte-identical
     * on every call, which is what lets the provider cache it.
     */
    public function system(Data $args): string
    {
        return implode("\n", [
            'You are the assistant inside '.config('app.name').', a web application.',
            'Answer in plain, direct prose. Two short paragraphs at most.',
            'Say you do not know rather than guessing. You have no access to the user data of this application.',
        ]);
    }

    public function prompt(Data $args): Prompt
    {
        return Prompt::make('Answer the question below.')
            ->with('question', $args->question);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(Data $args): array
    {
        // Prose, not a schema, so more room to breathe than the default.
        return ['temperature' => 0.6];
    }
}
