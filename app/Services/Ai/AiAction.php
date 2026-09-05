<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\User;
use App\Services\Ai\Contracts\AiTool;
use App\Services\Ai\Support\Prompt;
use App\Services\Auth\AuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate as GateFacade;
use Spatie\LaravelData\Data;

/**
 * One thing the application asks a model to do, named and authorized.
 *
 * The runner guarantees the order, exactly as it does for a query: build the
 * args through `Data::validateAndCreate()`, authorize(), build the prompt,
 * stream. A subclass fills in the pieces and nothing else calls them.
 *
 * The args class appears twice, in `@extends AiAction<AskAssistantArgs>` and
 * in `args()`. PHPStan checks the two agree, which is what makes `$args->…`
 * typed inside methods whose signature has to stay `Data $args` (PHP forbids
 * narrowing a parameter type in an override).
 *
 * Everything the user supplied belongs in {@see Prompt::with()}, never
 * concatenated into the instruction: fenced data is data, and inlined data
 * is one polite sentence away from being an instruction.
 *
 * @template TArgs of Data
 */
abstract class AiAction
{
    /**
     * The Data class the payload is validated into.
     *
     * @return class-string<TArgs>
     */
    abstract public static function args(): string;

    /**
     * Throw (AuthorizationException) when the user may not run this action
     * with these args. An empty body fails the architecture test.
     *
     * @param  TArgs  $args
     */
    abstract public function authorize(Authenticatable $user, Data $args): void;

    /**
     * The standing instruction: who the model is and what shape its answer
     * takes. Kept free of per-request data so the provider can cache it.
     *
     * @param  TArgs  $args
     */
    abstract public function system(Data $args): string;

    /**
     * The request itself.
     *
     * @param  TArgs  $args
     */
    abstract public function prompt(Data $args): Prompt|string;

    /**
     * Override to pin this action to a model. Null takes `config('ai.model')`.
     *
     * @param  TArgs  $args
     */
    public function model(Data $args): ?string
    {
        return null;
    }

    /**
     * Tools the model may call during this action. They run server-side with
     * no user watching, so each one authorizes what it touches.
     *
     * @param  TArgs  $args
     * @return array<int, AiTool>
     */
    public function tools(Data $args): array
    {
        return [];
    }

    /**
     * max_tokens, temperature, json. Anything left out takes the config
     * default.
     *
     * @param  TArgs  $args
     * @return array<string, mixed>
     */
    public function options(Data $args): array
    {
        return [];
    }

    /**
     * Deny unless the user holds the ability. For an action with no model of
     * its own; reach for gate() when there is one to point at.
     *
     * @throws AuthorizationException
     */
    protected function allow(Authenticatable $user, string $ability): void
    {
        if (! $user instanceof User || ! app(AuthorizationService::class)->can($user, $ability)) {
            throw new AuthorizationException;
        }
    }

    /**
     * The gate for the user, so authorize() reads as
     * `$this->gate($user)->authorize('view', $post)`.
     */
    protected function gate(Authenticatable $user): Gate
    {
        return GateFacade::forUser($user);
    }
}
