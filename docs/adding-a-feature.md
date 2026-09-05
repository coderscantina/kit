# Adding a feature

Walk it end to end with a `Post` feature: a list every member can read and a
create every editor can run.

## 1. Scaffold

```sh
php artisan make:feature Post
```

<!--@include: ./generated/make-feature.md-->

That writes `app/Features/Post/` with the model, factory, migration, data
class, policy, service provider and a policy test, plus
`resources/js/pages/posts/Index.vue` and its Vitest file. It also inserts, at
the `// kit:` marker lines:

- `posts.view` and `posts.manage` into `config/abilities.php`, and into the
  owner and admin role lists. Member is left alone on purpose; "everyone can
  see it" is a decision, not a default.
- the route into `resources/js/router/index.ts`
- the nav item and the access requirement into `resources/js/lib/access-control.ts`
- `nav.posts`, `posts.title` and `posts.empty` into both message files

Nothing is overwritten, so a second run only fills gaps.

Open the migration and give the table its real columns, then the model's
`#[Fillable]` and the factory to match. Migrations run from the feature's own
provider, so `php artisan migrate` picks them up with no further wiring.

## 2. A query

```sh
php artisan make:query posts.list
```

<!--@include: ./generated/make-query.md-->

You get `Queries/ListPost.php` and `Tests/ListPostTest.php`. Fill in `rules()`
with the arguments the client sends, and `handle()` with the read. `authorize()`
already points at the feature policy.

Declare `reads()` once the table is large or busy:

```php
public function reads(array $args): array
{
    return [Dep::eq('posts', 'author_id', $args['authorId'])];
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

`Mutations/CreatePost.php` runs inside a transaction the runner opened, with
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

## 4. Types and the page

```sh
php artisan types:generate
```

Now the client is typed. In `resources/js/pages/posts/Index.vue`:

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

## 5. Check it

```sh
bin/gate
```

Pint, PHPStan, PHPUnit, oxlint, `vue-tsc`, Vitest, the generated-types diff and
the generator round trip, in that order, stopping at the first failure. Green
here is green in CI.
