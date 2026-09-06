# Agent notes

The conventions for this repository are in [CLAUDE.md](CLAUDE.md). Read it
before changing anything, and run `bin/gate` before you call the work finished.

The four things it says that are easiest to get wrong:

- **The layout is layer-first.** `app/Models`, `app/Queries/<Domain>`,
  `app/Mutations/<Domain>`, `app/Actions/<Domain>`, `app/Data`,
  `tests/Feature/<Domain>`. There is no `app/Features`; it was removed on
  purpose and nothing registers anything any more.
- **Two data paths coexist.** Every surface that ships today is REST
  (controller, FormRequest, action, laravel-data, an `api` resource on the
  client). The reactive layer is what `make:feature` produces and no shipped
  page uses it yet. Match the surface, do not convert one to the other on the
  way past.
- **Generate, never hand-roll.** `make:feature`, `make:query`,
  `make:mutation`, `make:ai-action`, then `types:generate`. `bin/gate` ends by
  scaffolding a throwaway feature and running the whole suite against it, so a
  generator you broke fails the gate rather than the next person.
- **The accepted trade-offs at the bottom of CLAUDE.md are decisions.** Do not
  file them as findings.
