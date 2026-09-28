# History and files

Two traits turn a model into a record people can look back on and attach
things to. Both are addressed the way the client names a record: its table
and its id, `{ type: 'posts', id }`. The table resolves to `App\Models\Post`
by the same rule the generators name files by, so nothing is registered. The
trait is the opt-in: a type whose model lacks it is a 403, whatever the client
sends.

## History

```php
use App\Models\Concerns\Auditable;

class Post extends Model
{
    use Auditable;
}
```

`make:feature` adds it to every generated model. Each create, update, delete
and restore writes one row to `audit_entries`, in the same transaction as the
write, so a rolled-back save leaves no entry. The entry holds:

- the actor: whoever the request is authenticated as, a session or an access
  token. A queue job or a console command records none, which the history
  shows as "The system".
- the impersonator, when a root user is acting as someone else. Both names
  are shown.
- the changes: each field with its before and after. A create lists the
  fields it set, a delete the fields it had.

What is left out or hidden:

- The key, the timestamps, `deleted_at` and `version` are never recorded.
- A hidden attribute (`#[Hidden]`) or an encrypted cast is listed as changed,
  never with its values. That is how a password change shows up without the
  hash.
- `auditExclude()` drops a column entirely. `User` drops `last_login_at`,
  which changes on every sign-in and would bury the edits an admin made.
- String values are cut at 1000 characters. The history is a diff, not an
  archive of long text.

Show it with the component. It is live: a save on another screen adds its
line.

```vue
<RecordHistory type="posts" :id="post.id" />
```

Field names read `posts.fields.<field>`, the labels `make:feature` writes, and
fall back to the column name. The people page shows a person's history this
way.

Reading a history takes `view` on the record's policy. A record that has been
deleted falls back to `viewAny`, so whoever could list the rows can still see
what happened to one. `kit.audit.retention_days` (`AUDIT_RETENTION_DAYS`)
prunes old entries through the daily `model:prune`; unset keeps everything.

Model events are the trigger, so a write that bypasses them is not audited:
`DB::table()->update()`, `Model::query()->update()` and raw SQL. That is the
same boundary the reactive layer has, see [known
limitations](/limitations#non-eloquent-writes-are-invisible).

## Files

```php
use App\Models\Concerns\HasAttachments;

class Post extends Model
{
    use HasAttachments;
}
```

```vue
<RecordAttachments type="posts" :id="post.id" :editable="canEdit" />
```

Uploads are REST, a multipart body the reactive layer does not carry:
`POST /api/attachments` with `type`, `id` and `file`. The list is the
`attachments.list` query, so an upload, a delete or a finished preview shows
on every open screen. `editable` decides what the component offers; the
record's policy decides what the server allows: `view` opens the files,
`update` adds and removes them.

How files are kept:

- On a private disk (`ATTACHMENT_DISK`, `local` or `s3`) and read back
  through the app, like avatars. There is no public URL to leak.
- Under a ULID and an extension read from the bytes. The client's filename is
  kept for display and the download header only, so a GIF called `x.html` is
  stored and served as a GIF.
- `kit.attachments.extensions` is an allow list checked twice: `mimes`
  against the bytes and `extensions` against the name. SVG is not on it: it
  is a document that can carry script.
- Served with `Content-Security-Policy: sandbox`. A raster image or a PDF
  opens in the tab, anything else downloads.

Images get a 480 px WebP preview on the queue (`GenerateThumbnail`), made with
GD so the container needs no extra system library. The preview drops the
metadata, so a phone photo's GPS position never reaches it; the original is
kept byte for byte. An image over 40 megapixels keeps no preview rather than
risk the worker's memory.

Deleting a record deletes its files. A soft delete keeps them, so a restore
brings the record back whole. A file row is removed at once and its bytes
after commit, so a rollback cannot leave a row pointing at nothing.
