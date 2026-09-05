# Release procedure

## Branching

Trunk-based. Work on `feat/*`, open a pull request to `main`. `main` is
staging; production only ever comes from a CalVer tag.

Commit subjects are the changelog lines, so write them that way: a gitmoji, then
what changed in plain words. `bin/release` reads them straight into
`CHANGELOG.md`.

## Cut a release

```sh
bin/gate            # green locally first
bin/release --dry-run
bin/release
```

`bin/release` refuses to run off `main`, refuses when `main` is behind or has
diverged from `origin/main`, and refuses on a dirty tree. Then it:

1. Builds the tag `vYYYY.M.D-<shortsha>` from today's date and the content
   commit. No counter to compute, always unique, several a day is normal, and
   the sha is the audit trail back to the code.
2. Writes the changelog block from `git log <previous-tag>..HEAD`, excluding
   `🔖 Release` commits.
3. Commits the changelog and tags **that** commit, so the tagged tree contains
   its own release notes.
4. Pushes `main` and the tag.

`--backfill` rebuilds `CHANGELOG.md` from every existing tag.

## What the tag triggers

`.github/workflows/release.yml`:

1. Gates on `tests.yml` through `workflow_call`, the same suite pull requests
   run, so the gate cannot drift.
2. Skips the build when the tag is already in GHCR. Version tags are immutable
   and a re-run after a partial failure must not republish.
3. Builds with the GHA cache, `provenance: mode=max` and `sbom: true`.
4. Pushes `ghcr.io/<org>/<repo>:<tag>`, and `:production` only when this tag is
   the newest by `creatordate`. Re-running an old release must never regress
   production, which is why the checkout uses `fetch-depth: 0`.
5. Attests the build provenance.
6. Creates a GitHub deployment and POSTs the signed webhook:

   ```json
   {
     "event": "deploy",
     "environment": "production",
     "image": "...",
     "tag": "v2026.9.5-abc1234",
     "sha": "...",
     "changelogUrl": "...",
     "statusUrl": "..."
   }
   ```

   signed as `X-Kit-Signature: sha256=HMAC(body, DEPLOY_WEBHOOK_SECRET)`.

7. Waits up to 10 minutes for the receiver to post a deployment status. Without
   that wait the workflow goes green the moment the POST returns, whatever the
   host actually did.

A `notify-blocked` job posts to `NOTIFY_WEBHOOK_URL` when the gate fails. It
has to exist separately: the release job is _skipped_, not failed, when the
gate goes red, and a skipped job's own notification never fires.

Pushes to `main` run the same shape against `:staging` and
`DEPLOY_WEBHOOK_STAGING`.

## Secrets

| Secret                      | Used by                       |
| --------------------------- | ----------------------------- |
| `DEPLOY_WEBHOOK_STAGING`    | staging.yml                   |
| `DEPLOY_WEBHOOK_PRODUCTION` | release.yml                   |
| `DEPLOY_WEBHOOK_SECRET`     | both, and the receiver        |
| `NOTIFY_WEBHOOK_URL`        | release.yml, `notify-blocked` |

Each webhook step skips itself when its secret is unset, so a fresh clone does
not fail its first push.

## Rollback

Re-run the receiver with the previous tag:

```sh
KIT_IMAGE_TAG=v2026.9.4-def5678 \
  docker compose -f deploy/compose.prod.yml up -d --wait
```

The old image is still in GHCR, and its `APP_VERSION` differs from the recorded
one, so `kit:setup` runs on boot. Migrations do not roll themselves back: a
release that changed the schema destructively needs a forward fix, not a
rollback.
