# Tokens, MCP and webhooks

Two doors for other programs. Inbound, a model or a script calls the app's
reactive queries and mutations over MCP, authenticated by a personal access
token. Outbound, record changes are posted to webhook endpoints. Neither adds
an API of its own: MCP speaks the operations the screens already use, and
webhooks send the entries the [history](/records) already records.

## Access tokens

**Account → API access** mints them. A token has a name, the abilities it
may use and an expiry of 30, 90 or 365 days, or none. It is shown once;
only its SHA-256 is stored.

- A token opens `/mcp` and nothing else. `auth:sanctum` would accept a bearer
  token on every route it guards, the SPA's own API included, so
  `AppServiceProvider::configureAccessTokens()` refuses it anywhere but the
  `mcp` route. A leaked token cannot export the account or read its sessions.
- Its abilities are a subset of the owner's at creation, and they are checked
  against the owner's abilities again on every use.
  `AuthorizationService::can()` takes the smaller of the two, root included:
  a root user's token carries what it was given, not everything.
- `app.access` is added to every token, because every authenticated route
  checks it.
- Minting takes a fresh password confirmation, is refused under
  impersonation, lands in the security trail and sends the owner a security alert.

## MCP

`POST /mcp` speaks MCP over streamable HTTP (`laravel/mcp`). Three tools:

| Tool              | Does                                                                          |
| ----------------- | ----------------------------------------------------------------------------- |
| `list_operations` | every query and mutation, with its docblock summary and its arguments         |
| `run_query`       | runs a query as the token's owner, answers the result as JSON                 |
| `run_mutation`    | runs a mutation in its transaction; the change is pushed and audited as usual |

Three tools rather than one per operation: the catalogue grows with every
generated feature, and a model reads one list more reliably than it scans
fifty tools. Nothing is written by hand, so a query `make:query` just wrote
is callable at once. The description comes from the first paragraph of the
class docblock, so write that paragraph for a reader who has only the name.

Calls go through the same runners as `/rq/*`: args validated into the Data
class, `authorize()` against the narrowed abilities, a mutation in its
transaction. A refusal, a 422 or a 409 comes back as a tool error the model
can read, not a protocol error.

Point a client at it with the snippet the API access page shows:

```json
{
  "mcpServers": {
    "kit": {
      "type": "http",
      "url": "https://app.example.com/mcp",
      "headers": { "Authorization": "Bearer <token>" }
    }
  }
}
```

## Webhooks

**Webhooks** in the sidebar, behind `webhooks.manage` (owner and admin).
An endpoint has a URL, the events it listens for and a signing secret.

Events are `<table>.<created|updated|deleted|restored>`, plus `posts.*` for
a whole type and `*` for everything. Every model that uses `Auditable` adds
its events; `restored` only exists for a soft-deleting model. The payload
carries the same redacted changes the history shows, never the whole row:

```json
{
  "type": "posts.updated",
  "timestamp": "2026-09-19T07:31:37+00:00",
  "data": {
    "object": "posts",
    "id": "01m2w97v1zgwxzev561z6vvwxv",
    "changes": { "title": { "before": "Draft", "after": "Launch" } },
    "actorId": "01m2w97cwdvnhmcssb66z4d8sx"
  }
}
```

Deliveries are signed the [Standard Webhooks](https://www.standardwebhooks.com)
way, so a receiver verifies with any of its libraries: `webhook-id` (the
delivery id, stable across retries, so a duplicate can be dropped),
`webhook-timestamp`, and `webhook-signature: v1,<base64 HMAC-SHA256 of
"id.timestamp.body">` keyed with the base64 part of the `whsec_` secret. The
secret is shown after a create and after a rotation, never in between.

A pending `webhook_deliveries` row is the queue entry. Creating it queues
`DeliverWebhook` after the transaction commits, so a rolled-back write sends
nothing, and a record change and a test ping take the same path. A non-2xx
answer or no answer is retried after 10 s, 1 min, 5 min, 30 min and 2 h, then
marked failed. The delivery log beside each endpoint is live and shows the
exact body sent and what came back. Deliveries are pruned after
`WEBHOOKS_RETENTION_DAYS` (30).

## Outbound URLs

`OutboundUrlGuard` stands between a webhook and the network. It allows http
and https only, resolves the host, and refuses private, loopback, link-local,
carrier-grade NAT and reserved addresses, including their IPv4-mapped and
NAT64 forms. The sender pins the connection to the addresses it approved
(`CURLOPT_RESOLVE`) and follows no redirects, so neither a second DNS answer
nor a 302 reaches the metadata endpoint. The URL is checked when it is saved
and again before every attempt, because DNS can change in between.

`WEBHOOKS_ALLOW_PRIVATE_TARGETS=true` lets a local stack post to a receiver
on localhost. Leave it off anywhere else. Anything else that requests a
user-supplied URL goes through the same guard.
