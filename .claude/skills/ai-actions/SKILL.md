---
name: ai-actions
description: The AI contract. Use when adding or changing an AI action, a prompt, a model tool, useAiStream, or the assistant and conversation UI.
---

# AI actions

`docs/ai.md` is the full contract.

An AI action is a class: `php artisan make:ai-action <feature>.<verb>` writes it to `app/Ai/Actions/`, its args class to `app/Data/` and its test to `tests/Feature/<Feature>/`. There is one endpoint, `/api/ai/stream`, and the action name selects the action; a prompt never gets its own controller.

The shape mirrors a query: `args()` names a laravel-data class (checked against `@extends AiAction<XArgs>` by PHPStan), `authorize()` does real work, `system()` is the standing instruction and carries no per-request data so the provider can cache the prefix, `prompt()` returns the request.

Everything the user or the database supplied goes through `Prompt::with()`, which fences it under a nonced tag; the instruction string holds only your own text. A tool runs server-side with nobody watching, so it authorizes every row it touches; an id the model produced is not proof.

The client calls `useAiStream('<name>')` from `~/composables/useAiStream`, typed off `Kit.AiMap`; it is the only caller of `/api/ai/stream`.

A conversation is composed, not generated: `message`, `bubble`, `message-scroller`, `marker`, `attachment` and `questionnaire` in `~/components/ui`. `resources/js/pages/ai/Assistant.vue` is the shipped example. There is no conversation store; the transcript is the page's.

Test with `FakeAiDriver::swap(...)`: no network, no key, no bill.
