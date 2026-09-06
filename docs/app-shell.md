# App shell

The shell is what a user sees before your feature does: a sidebar, a header, a
command palette and the auth pages. It is built to be re-shaped from one file,
so a derived app rebrands without forking a component.

That file is `resources/js/lib/app-config.ts`.

## The one file

```ts
export const appConfig = defineAppConfig({
  brand: {
    name: 'Kit',
    shortName: 'K',
    icon: 'lucide:box',
    homeRouteName: 'dashboard',
  },
  sidebar: {
    sections: [
      { items: ['dashboard'] },
      { labelKey: 'nav.sections.manage', items: ['users', 'assistant'] },
      { labelKey: 'nav.sections.account', items: ['account-profile', 'account-security'] },
    ],
    overflowSection: 1,
    collapsible: true,
    defaultCollapsed: false,
    resizable: true,
    width: { default: 248, min: 208, max: 360 },
  },
  features: {
    commandPalette: true,
    shortcutsHelp: true,
    colorModeToggle: true,
    localeSwitch: true,
    breadcrumbs: true,
    pageSearchShortcut: true,
  },
  density: 'comfortable',
  defaultColorMode: 'system',
  titleTemplate: (title) => `${title} · Kit`,
})
```

`defineAppConfig` is identity with a type annotation, so a typo is a build
error rather than a shell that quietly loses its sidebar.

### Brand

`brand.icon` takes any Iconify name. For a real logo, import an SVG as a
component and pass it as `brand.logo`; it wins over the icon, and it is used in
the sidebar, on the auth pages and nowhere else.

```ts
import Logo from '~/assets/logo.svg?component'

brand: { name: 'Acme', logo: Logo, icon: 'lucide:box', homeRouteName: 'dashboard' }
```

### Sidebar sections

Sections **group** navigation, they do not define it. What exists and who may
see it stays in `lib/access-control.ts`, which is what `make:feature` appends
to at the `// kit:nav` marker.

That split is deliberate: a route name that appears in no section falls into
`overflowSection`, so a generated feature shows up in the sidebar on its own.
Reordering a section is a config edit; adding a page is not.

A group whose items are all filtered out by access control disappears, heading
included.

### Feature toggles

Each flag removes an affordance and everything attached to it: turning
`commandPalette` off drops the palette, its header trigger and the `⌘K`
binding, so nothing is left pointing at a dialog that will not open.

### Density

`density` sets `data-density` on `<html>`, which moves the geometry tokens in
`assets/css/app.css`: header height, shell gutter, nav row height. Type,
colour and radius do not change with it. `compact` trims roughly 20%.

### Default colour mode

`defaultColorMode` only applies before the visitor has chosen; after that
`localStorage` wins. If you change it away from `system`, also change the
inline script in `resources/views/app.blade.php`. That script runs before any
JavaScript module exists, and without the matching change every cold load
flashes the wrong theme.

## What a page gets

### Title and breadcrumbs

```vue
<script setup lang="ts">
import { usePageMeta } from '~/composables/usePageMeta'

usePageMeta(() => ({
  title: post.value?.title ?? t('posts.loading'),
  breadcrumbs: [{ label: t('nav.posts'), to: { name: 'posts' } }, { label: post.value.title }],
}))
</script>
```

Pass a getter when the title depends on loaded data. Without `breadcrumbs` the
header shows one crumb, taken from the page's navigation item. The document
title comes from the same place, through `titleTemplate`.

One page owns the header at a time. A page that unmounts after its successor
has mounted does not blank the new title.

### Header actions

```vue
<PageActions>
  <Button variant="accent" @click="create">New post</Button>
</PageActions>
```

Teleports into the header next to the breadcrumbs. A page is rendered by
`<RouterView>`, so it has no layout slot to fill; this is the seam. The
teleport is deferred, because the header is patched into the DOM in the same
pass as the page. A page test that mounts the page without the shell needs an
`#app-header-actions` element in the document, or Vue warns and drops the
actions.

### Keyboard shortcuts

```ts
useShortcut({
  keys: 'mod+s',
  scope: 'editor',
  description: () => t('editor.save'),
  handler: save,
})
```

Scope is component lifetime: while the editor is mounted its `mod+s` shadows
any global binding, and unmounting releases the key. `mod` is Cmd on macOS and
Ctrl elsewhere. Every binding shows up in the `?` overlay automatically, so the
help is generated rather than maintained.

Shortcuts do not fire while focus is in a text field or inside a dialog unless
you pass `allowInInput` / `allowInOverlay`.

The shell binds `mod+b` (sidebar), `mod+k` (palette), `?` (help) and `/`
(page search). `/` focuses the first `[data-shortcut-search]` or
`input[type="search"]` in `<main>`, so a page opts in by marking its field:

```vue
<Input data-shortcut-search type="search" v-model="term" />
```

### Full-bleed pages

`<main>` applies the shell gutter. A page that needs the whole width marks its
root instead of nesting in a second layout component:

```vue
<template>
  <div data-bleed>…</div>
</template>
```

The gutter is removed by `main:has(> [data-bleed])` in `app.css`.

## Accessibility the shell already handles

- A skip link, first in the tab order.
- `<main id="main-content" tabindex="-1">`, focused after every route change,
  with the new page title announced in a live region.
- `aria-current="page"` on the active nav item, from `RouterLink`.
- A single focus treatment: a 2px `--ring` outline, offset, on every focusable
  element. In dark mode the ring is `blue-400`, which clears 4.5:1 on the
  darkest surface; `blue-500` does not.
- Everything that animates collapses under `prefers-reduced-motion: reduce`.
  Opacity fades stay, because losing them makes overlays snap harder than the
  motion they replace.

## Auth pages

`layouts/UnauthenticatedLayout.vue` is a two-column split: a brand panel that
disappears below `lg`, and the form. All the brand-panel copy lives under
`auth.marketing.*` in `i18n/en.json`, so rewriting it is a message edit.

Each page is `components/app/AuthPanel.vue` plus a form. The panel takes a
title, an optional description and a `footer` slot for the cross-link.

## What is not here

- **View transitions.** Tried and reverted; the router comment says so. Do not
  reintroduce them without reading that first.
- **Social login and magic links.** No backend endpoint exists, so no button
  points at one.
