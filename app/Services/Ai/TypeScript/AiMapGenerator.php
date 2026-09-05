<?php

declare(strict_types=1);

namespace App\Services\Ai\TypeScript;

use App\Services\Ai\Registry\AiCatalog;
use Spatie\LaravelData\Data;

/**
 * Emits the `Kit.AiMap` declaration: every registered action with the type
 * of the payload it takes. That is what makes `useAiStream('assistant.ask')`
 * a compile error the moment the action is renamed or removed.
 *
 * The args class carries #[TypeScript], so the transformer has already
 * written it as a global `App.*` type and this only has to name it.
 */
final class AiMapGenerator
{
    public function __construct(
        private readonly AiCatalog $catalog,
    ) {}

    public function generate(): string
    {
        $lines = [];

        foreach ($this->catalog->actions() as $name => $class) {
            /** @var class-string<Data> $argsClass */
            $argsClass = $class::args();

            $lines[] = "    '{$name}': {\n      args: {$this->typeName($argsClass)}\n    }";
        }

        $body = $lines === [] ? '' : "\n".implode("\n", $lines)."\n  ";

        return "declare namespace Kit {\n  export type AiMap = {{$body}}\n}\n";
    }

    /**
     * `App\Ai\Data\AskAssistantArgs` → `App.Ai.Data.AskAssistantArgs`, the
     * name the GlobalNamespaceWriter gives it.
     */
    private function typeName(string $class): string
    {
        return str_replace('\\', '.', ltrim($class, '\\'));
    }
}
