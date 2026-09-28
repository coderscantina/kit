---
name: records
description: The record contract. Use when adding history or an audit trail to a model, showing what changed and who changed it, or attaching, uploading, previewing or serving files on a record.
---

# Records

`docs/records.md` is the full contract.

A record is addressed as `{ type, id }`, where `type` is the table. `App\Support\Records` resolves `posts` to `App\Models\Post` by name, and the trait is the opt-in: `Auditable` for history, `HasAttachments` for files. Authorization is always the record's own policy, `view` to read, `update` to attach or remove.

## History

`Auditable` writes `audit_entries` from model events, inside the write's transaction. Generated models already use it. Hidden and encrypted attributes are recorded as changed without values; a column that changes on its own (a last-seen stamp) goes in `auditExclude()`. The audit entry is also what webhooks send, so auditing a model publishes its changes to every endpoint listening for `<table>.*`.

`<RecordHistory type="posts" :id />` renders the live `audit.history` query. Field labels come from `<type>.fields.<field>`.

## Files

Uploads are REST (`api.attachments.upload`, with progress); the list is the live `attachments.list` query. `<RecordAttachments type="posts" :id :editable />` does both. Storage, content-sniffed extensions, the sandbox header and the queued GD preview live in `AttachmentStorage` and `GenerateThumbnail`; widen the allow list in `kit.attachments.extensions`, never by skipping `mimes`.

## Tests

`tests/Fixtures/Records/Widget.php` is a model with both traits and an owner-only policy. `Widget::install()` in `setUp()` aliases it to `App\Models\Widget` and creates its table, so a test addresses it as `widgets`.
