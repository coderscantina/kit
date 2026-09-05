# Frontend tests

Vitest + Vue Test Utils on jsdom. `bun run test`, `bun run test:watch`.

Config lives in `vitest.config.ts`. Tests live here, mirroring `resources/js/`
(`tests/js/lib/`, `tests/js/api/`, `tests/js/components/`), one file per source
module named `<module>.test.ts`. The reactive client package keeps its own tests
in `packages/reactive-vue/tests/`.

## Conventions

- Always import `describe`/`it`/`expect`/`vi` from `vitest`. Globals are off.
- Group with `describe` per exported function or behaviour area.
- Name tests by behaviour: `'drops the patch once the push carries its mutation id'`.
- Assert on output, not internals: the returned value, the rendered DOM, the
  request that was sent. Never on a private field.
- Cover the edge cases the module actually guards: empty input, out-of-order
  events, the retry that must happen exactly once.
- Comment only what the assertion cannot say.

## Environment

`tests/js/setup.ts` runs before every file and provides:

- `window.__APP_CONFIG__`, pinned before any import. Several modules read it at
  module load. Realtime is off, registration open.
- `ResizeObserver`, pointer capture and `scrollIntoView` stubs, without which a
  reka-ui listbox never opens.
- An in-memory `localStorage`. Node's own global shadows jsdom's and every
  storage-backed composable silently degrades to a detached ref without it.
- The real i18n instance, installed globally. Assert on real English copy, not
  on keys, so a missing key fails the test instead of echoing back.

## Harness

`tests/js/support/harness.ts` exports `withSetup(composable, { seed })`. It runs
the composable inside a real component with `VueQueryPlugin` and seeds the
query cache by key, so the keys stay under test.

```ts
let harness: Harness<ReturnType<typeof useThing>> | undefined

afterEach(() => {
  harness?.unmount()
  harness = undefined
})

harness = withSetup(() => useThing(), { seed: [[queryKeys.me(), me]] })
```

Type the harness explicitly. `ReturnType<typeof setup>` is circular and
TypeScript silently resolves it to `any`, so the file typechecks while asserting
nothing.

## Mocking

Mock at the transport boundary: `fetch`, the reactive transport, Echo. Never the
function under test's own helpers. A `Response` body can only be read once, so
use `mockImplementation(async () => new Response(...))`, not `mockResolvedValue`.
