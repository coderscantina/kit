# Adding a feature

Walk it end to end with a `Post` feature: a list every member can read and a
create every editor can run.

## 1. Scaffold

```sh
php artisan make:feature Post --versioned --fields="title:string body:text? published_at:datetime?"
```

<!--@include: ./generated/make-feature.md-->

`--fields` names the columns once, as `name:type` with a trailing `?` for
nullable. The types are `string`, `text`, `integer`, `boolean`, `date` and
`datetime`; without the option the feature gets one `name` string. Every file
below follows the list: the migration's columns, the model's `#[Fillable]` and
casts, the factory, the data class and its `fromModel()`, the page's columns
and the `posts.fields.*` labels. `--versioned` adds the `version` column and
the `Versioned` trait, which a row edited in a form needs (step 3).

That writes the feature across the layers, every file named after it:

| File                                           | Is                         |
| ---------------------------------------------- | -------------------------- |
| `app/Models/Post.php`                          | the model                  |
| `app/Policies/PostPolicy.php`                  | the policy, found by name  |
| `app/Data/PostData.php`                        | what queries serialize to  |
| `database/factories/PostFactory.php`           | the factory, found by name |
| `database/migrations/*_create_posts_table.php` | the table                  |
| `tests/Feature/Post/PostPolicyTest.php`        | the policy test            |
| `resources/js/pages/posts/Index.vue`           | the page                   |
| `tests/js/pages/posts/Index.test.ts`           | its Vitest file            |

Nothing is registered anywhere. Laravel resolves `App\Models\Post` to
`App\Policies\PostPolicy` and `Database\Factories\PostFactory` by name, and the
migration is already in the directory `php artisan migrate` reads.

The command then runs `make:query posts.list` and `types:generate`, so the page
renders real rows from the moment it exists rather than a placeholder. It also
inserts, at the `// kit:` marker lines:

- `posts.view` and `posts.manage` into `config/abilities.php`, and into the
  owner and admin role lists. Member is left alone on purpose; "everyone can
  see it" is a decision, not a default.
- the route into `resources/js/router/index.ts`
- the nav item and the access requirement into `resources/js/lib/access-control.ts`
- `nav.posts`, `posts.title` and `posts.empty` into both message files

Nothing is overwritten, so a second run only fills gaps.

The create migration stays the one place a column is declared. The later
generators read the fields back from it, so a column added there shows up in
the next query, mutation or endpoint you generate.

## 2. A query

`make:feature` already wrote `posts.list`. Run the generator directly for any
further query:

```sh
php artisan make:query posts.comments
```

<!--@include: ./generated/make-query.md-->

You get `app/Queries/Post/ListPost.php` and
`tests/Feature/Post/ListPostTest.php`. Fill in `handle()` with the read; `authorize()` already points at the feature policy. Re-running
the generator on a query that exists reports what it left alone.

A generated query starts on `NoArgs`. To give it arguments, write a Data class
and name it in two places, which PHPStan checks against each other:

```php
#[TypeScript]
final class ListPostArgs extends Data
{
    public function __construct(public string $authorId) {}
}

/**
 * @extends Query<ListPostArgs>
 */
#[ReactiveQuery('posts.list', result: PostData::class, list: true)]
final class ListPost extends Query
{
    public static function args(): string
    {
        return ListPostArgs::class;
    }
}
```

The parameters stay `Data $args`, because PHP does not allow a subclass to
narrow a parameter type. PHPStan reads the concrete type off the `@extends`, so
`$args->authorId` is typed and a typo is an error.

Declare `reads()` once the table is large or busy:

```php
public function reads(Data $args): array
{
    return [Dep::eq('posts', 'author_id', $args->authorId)];
}
```

Without it every write to `posts` queues a recompute for every subscriber. With
it, only writes to rows with that `author_id` do.

The generated test asserts both halves of the contract: a row change pushes the
new result, and a user without `posts.view` gets a 403 from `/rq/subscribe`.

## 3. A mutation

```sh
php artisan make:mutation posts.create
```

<!--@include: ./generated/make-mutation.md-->

`app/Mutations/Post/CreatePost.php` and `app/Data/CreatePostArgs.php` come out
together, with one validated property per column and a write that fills them.
The mutation runs inside a transaction the runner opened, with
deadlock retry and after-commit invalidation. Do not call `DB::transaction`,
`afterCommit` or dispatch an invalidation inside it; a PHPStan rule fails the
build if you do. To branch on a row you are about to change, take it under
lock:

```php
$post = $this->lock(Post::query()->findOrFail($args['id']));
```

The generator also adds `mutations.posts.create.error` to both message files,
because `useReactiveMutation` always translates that key and a missing one
would echo back at the user.

A mutation that edits a row two people can have open states the version it
read:

```sh
php artisan make:mutation posts.update --versioned
```

The args carry `id` and `version`, and `handle()` locks against it:

```php
$post = $this->lockVersion(Post::query()->findOrFail($args->id), $args->version);
```

A row that moved answers 409 with the current row instead of being
overwritten; the generated test covers both. The feature needs the
`Versioned` trait and the `version` column, which `make:feature --versioned`
wrote; the generator says what to add when they are missing. It also writes
`resources/js/components/posts/UpdatePostDialog.vue`, the client half below.
`docs/architecture.md` has the payload.

## 4. Types and the page

```sh
php artisan types:generate
```

`make:feature` ran this once already; run it again after every change to a
data class, an args class, a query or a mutation, and commit the result.

`resources/js/pages/posts/Index.vue` already lists `posts.list`. Extend it:

```ts
import { useReactiveMutation, useReactiveQuery } from '~/lib/reactive'

const posts = useReactiveQuery('posts.list', { authorId })
const create = useReactiveMutation('posts.create', {
  optimistic: (cache, args) => {
    cache.patch(['posts.list', { authorId }], (list) => [draft(args), ...list])
  },
})
```

The name is a literal union off `Kit.ReactiveMap`, so a typo is a type error
and the args and result infer. Never `fetch` `/rq/*` directly.

A form on one of those rows binds to it with `useReactiveForm`, so a
colleague's save lands in the fields the user has not touched while he is
typing:

```ts
import { useReactiveForm, useReactiveMutation } from '~/lib/reactive'

const form = useReactiveForm(() => post.value, { fields: ['title', 'body'] })
const update = useReactiveMutation('posts.update', {
  onConflict: (error) => form.apply(error.current),
})

const save = () =>
  update.mutate({ id: form.base.value!.id, version: form.base.value!.version, ...form.values() })
```

Bind `form.fields.title` with `v-model` as you would a ref. `form.conflicts`
lists the fields both sides changed, each with the server's value, and
`form.accept('title')` takes it. The generated `UpdatePostDialog` is this, with
an input per column; open it from a row with
`<UpdatePostDialog v-model:open="editing" :row="post" />`.

## A REST endpoint

What the browser posts a form to, uploads a file to or is redirected back to
stays REST:

```sh
php artisan make:endpoint posts.import
```

<!--@include: ./generated/make-endpoint.md-->

That writes an invokable controller, its FormRequest and the action behind it
under `Posts/`, adds `POST /api/posts/import` to `routes/app.php`, a method to
`resources/js/api/resources/posts.ts` (registered on `api` the first time),
and a test for the 201, the 422 and the 403. It creates a row from the columns
until you give the action its real work.

A REST list filters through `make:filter Post`, which writes the filter with
its sort allow list and whitelist. [Lists](/lists) has the contract.

## 5. Ask a model about it

A feature that wants a model does not get a controller either:

```sh
php artisan make:ai-action posts.summarize
```

That writes `app/Ai/Actions/SummarizePost.php`, its args class and a test that
runs it against a faked model. The client streams it with
`useAiStream('posts.summarize')`. The whole contract, including tools and the
prompt-injection rules, is in [AI](/ai).

## 6. Check it

```sh
bin/gate --changed   # while working
bin/gate             # before calling it done
```

Pint, PHPStan, PHPUnit and the generated-types diff in one lane, oxlint,
oxfmt, `vue-tsc` and Vitest in the other, then the generator round trip. Each
check prints one line; a failing one prints its output. `--changed` scopes the
formatters and Vitest to what changed since `main` and runs the round trip
only when something it reads moved.

One gap: `tests/Reactive` needs MySQL and Redis, and skips itself when
`REACTIVE_TEST_DB` is unset. `bin/gate` prints a yellow line when that happens,
and CI runs the suite for real. Set the `REACTIVE_TEST_DB*` and
`REACTIVE_TEST_REDIS*` variables to cover it locally too.
