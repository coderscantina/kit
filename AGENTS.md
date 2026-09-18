# Agent notes

The conventions for this repository are in [CLAUDE.md](CLAUDE.md). Read it
before changing anything. The contracts it points to are plain markdown in
`.claude/skills/<name>/SKILL.md`; read the one for the area you touch.

Claude Code runs project hooks that other agents do not get. Without them:

- run `php artisan types:generate` after changing a data class, query,
  mutation or AI action, and commit `resources/js/types/generated.d.ts`
- format what you write: `vendor/bin/pint <file>` for PHP, `bunx oxfmt <file>`
  for the frontend
- leave `resources/js/types/generated.d.ts` and `auto-imports.d.ts` to their
  generators

Run `bin/gate --changed` while working and `bin/gate` before you call the work
finished. The accepted trade-offs at the bottom of CLAUDE.md are decisions, not
findings.
