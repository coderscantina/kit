---
name: machine-access
description: The machine-access contract. Use when working on personal access tokens, the MCP server or its tools, outgoing webhooks, webhook signing or retries, or any server-side request to a user-supplied URL.
---

# Machine access

`docs/machine-access.md` is the full contract.

## Tokens

A personal access token authenticates `/mcp` and nothing else: `AppServiceProvider::configureAccessTokens()` rejects it on every other route, and a test pins that. `AuthorizationService::can()` intersects the token's abilities with the owner's, root included. Authorization stays in policies and `authorize()`; the intersection reaches them without a change.

## MCP

`App\Mcp\KitServer` exposes `list_operations`, `run_query` and `run_mutation` over the reactive catalogue. A new query or mutation is callable without touching `app/Mcp`; its `list_operations` description is the first paragraph of its class docblock, so write that paragraph for a model that sees only the name. Keep the tool count at three; a new capability is a new query or mutation.

## Webhooks

Events come from `Auditable`: `AuditLog` hands each entry to `WebhookFanOut`, which writes one pending `WebhookDelivery` per listening endpoint. Creating a delivery row queues `DeliverWebhook` after commit, so a new kind of event is a new delivery row, never a direct dispatch. Signing follows Standard Webhooks (`WebhookEndpoint::signatureHeaders()`); the secret leaves the server only in the `webhooks.create` and `webhooks.rotateSecret` results.

## Outbound requests

Every request to a user-supplied URL goes through `OutboundUrlGuard`: `assertSafe()` before each attempt, `curlResolveFor()` to pin the connection, redirects off. Validate the URL on save with the `PublicUrl` rule. `WEBHOOKS_ALLOW_PRIVATE_TARGETS` is for a local receiver only.
