# Design tokens

The app has one stylesheet that decides what is in front of what:
`resources/js/assets/css/app.css`. Every colour a component uses is a token
from it. A component never names a palette shade, so the whole look can be
retuned in that one file, per mode, per accent, without touching a `.vue`.

## The gray ramp

The grays are custom oklch, hue ~278, low chroma. The shell's surfaces live in
the two ends of the ramp, so those ends sit on a fine, perceptually even grid
and the middle, which only carries text, stays coarse.

| Region | Steps                                  | Grid            |
| ------ | -------------------------------------- | --------------- |
| light  | 25, 50, 75, 100, 125, 150, 175, 200    | 1.7 points of L |
| text   | 250, 300, 400, 500, 600, 700           | 6 points and up |
| dark   | 750, 800, 825, 850, 875, 900, 925, 950 | 2.75 points     |

| Step | L    | Hex     |     | Step | L     | Hex     |
| ---- | ---- | ------- | --- | ---- | ----- | ------- |
| 25   | 98.3 | #f9f9fb |     | 700  | 39.21 | #424553 |
| 50   | 96.6 | #f3f3f6 |     | 750  | 34.38 | #363846 |
| 75   | 94.9 | #edeef2 |     | 800  | 31.63 | #2f313c |
| 100  | 93.2 | #e7e8ee |     | 825  | 28.88 | #292a34 |
| 125  | 91.5 | #e1e2e9 |     | 850  | 26.13 | #23242b |
| 150  | 89.8 | #dcdde4 |     | 875  | 23.38 | #1c1d23 |
| 175  | 88.1 | #d6d7df |     | 900  | 20.63 | #16171b |
| 200  | 86.4 | #d0d2da |     | 925  | 17.89 | #111114 |
| 250  | 80.3 | #bcbecb |     | 950  | 15.15 | #0b0b0d |

The rule the ramp exists to serve: a rung and the fill or hairline that sits on
it are one grid step apart, so nothing in the shell jumps.

## The ladder

Depth is a ladder with one rung per level of nesting. It runs in the same
direction in both modes: the frame is furthest back, the canvas is the stage,
and a block that is boxed or floats sits one rung above whatever it is on.

| Rung | Role                                                             | Class                                             | Light    | Dark     |
| ---- | ---------------------------------------------------------------- | ------------------------------------------------- | -------- | -------- |
| L0   | the frame: sidebar, header, tab bar, drawer, the auth brand half | `bg-sidebar`                                      | gray-100 | gray-925 |
| L1   | the canvas: body, main                                           | `bg-surface`                                      | gray-50  | gray-900 |
| L2   | a boxed block on the canvas: Card, Table, Dialog, Sheet          | `bg-card border-border shadow-soft-sm`            | white    | gray-875 |
| L3   | floating: menu, select, tooltip, palette, toast                  | `bg-popover border-popover-border shadow-soft-lg` | white    | gray-850 |
|      | the scrim under a dialog                                         | `bg-overlay`                                      |          |          |

Light mode is a gray-50 canvas with white blocks on it. A boxed block gets a
hairline and `shadow-soft-sm` under it, a two-layer shadow that settles the
block without lifting it; the frame is two grid steps below the canvas, so
the chrome reads as chrome and not as more page. A
white canvas was tried and read as flat: the white blocks had nothing to
stand on. The full shadow the cards used to float on is gone, the tint and
the hairline do that work now. Dark mode has no shadow worth seeing,
so every rung is one grid step (2.75 points) lighter than the rung below it,
and the card carries the same hairline so it does not lean on the step alone.

The rule when building a screen: pick the rung by nesting, not by taste. A
table on the page is `bg-card`. A table inside a card sinks back to
`bg-surface`. A menu that opens from either is `bg-popover`. A dialog is
`bg-card` over the scrim, and the select inside it is `bg-popover`, so the
select still reads as floating above the dialog.

### The header is the frame

The header and the mobile tab bar are `bg-sidebar` with `border-sidebar-border`,
at 85% with a blur. They are chrome, not page. On a desktop the header's left
edge meets the sidebar's top edge at a corner, and two tones meeting there put
a seam through the shell for no reason; one tone makes the chrome an L around a
clean canvas. It also gives the header something to be: on the canvas colour it
was invisible except for its hairline, which is why it read as weak.

Whatever the header does, the tab bar does. The tab bar's active pill is
`bg-sidebar-accent` with `text-sidebar-accent-foreground`, the same pair the
sidebar's active nav item uses, so the two phone-and-desktop halves of the
frame agree.

## Fills

A fill is not a rung. It sits on a rung and tints it. A passive fill is one
grid step off the white it sits on and a control at rest is two; more than
that and a table header reads as a bar.

| Token              | Use                                                       | Light    | Dark     |
| ------------------ | --------------------------------------------------------- | -------- | -------- |
| `secondary`        | a control at rest, and the hover of a flat one            | gray-100 | gray-800 |
| `muted-background` | a passive fill: table header, tab list, `kbd`, code       | gray-75  | gray-850 |
| `input`            | a field                                                   | white    | gray-850 |
| `elevated`         | a raised chip on a fill: the active tab, the switch thumb | white    | gray-700 |

Dark `secondary` sits at 800 rather than level with the popover, so a hover
inside a menu still shows. Dark `input` sits level with the popover and is read
from its border there. `elevated` is the one token that goes to the top of the
ramp rather than one step, because a chip on a fill has to be clearly lighter
than the fill, and in dark mode there is no shadow to say so.

## Hairlines

Four weights, weakest first. Naming them in order is the point: a component
picks the weight by what the line has to do.

| Token            | Use                                                  | Light    | Dark     |
| ---------------- | ---------------------------------------------------- | -------- | -------- |
| `popover-border` | the edge of something floating                       | gray-100 | gray-800 |
| `border`         | a divider, a card edge, the default `*` border       | gray-150 | gray-825 |
| `input-border`   | the edge that makes a field a field                  | gray-175 | gray-800 |
| `border-strong`  | a line that carries alone: switch track, header rule | gray-200 | gray-750 |
| `sidebar-border` | the frame against the canvas                         | gray-150 | gray-875 |

`popover-border` is the lightest in light mode and stronger than `border` in
dark. Same job, opposite direction: in light the shadow does the floating and a
hard edge on top of it is what made menus look heavy, in dark there is no
shadow so the border is all there is.

The light borders used to be gray-200 at L 82.9, roughly Tailwind's gray-350.
That is a rule, not a hairline, and it was the single loudest thing on a white
card. It also double-counted the sidebar seam: the frame already steps away
from the canvas by tone, so drawing a dark line on the boundary as well made
the shell look pieced together.

## Shadows

`shadow-soft-sm` sits under a card and a table: two layers, 6px reach, about
9% of ink. The hairline is the edge, the shadow only settles it. `shadow-soft`
is for a block raised on purpose, like the accent card. `shadow-soft-lg` is what makes a
menu float: four layers reaching 24px, about 13% of ink in total. It used to be
six layers reaching 33px at twice the ink, which read as a smudge under every
dropdown.

## The scrim

Every modal surface dims the page with the same tone. `overlayClass` in
`resources/js/components/ui/overlay.ts` is that one string: `bg-overlay`, a
`backdrop-blur-xs`, and the fade. Dialog, alert dialog, scroll dialog and sheet
all import it, so opening a confirm from a dialog does not step the background
through two different greys. A surface that needs a different stacking order
merges over it (`cn(overlayClass, 'z-40')`) rather than writing its own.

## Utilities

Four things `app.css` adds that Tailwind does not ship, all of them for content
that scrolls or is still being written.

| Utility                             | What it does                                                                   |
| ----------------------------------- | ------------------------------------------------------------------------------ |
| `scroll-fade-x` / `scroll-fade-y`   | Fades an edge only while there is content past it, driven by a scroll timeline |
| `scrollbar-none` / `scrollbar-thin` | Hides the scrollbar, or keeps it out of the way                                |
| `scrollbar-gutter-stable`           | Reserves the track, so content does not shift when the bar appears             |
| `shimmer`                           | A highlight travelling across text whose value is still arriving               |

`shimmer` reads off `currentColor`, so it needs no colour of its own; it is
what an attachment mid-upload and a title a model is writing both use. Under
`prefers-reduced-motion` it stops, like everything else that moves.

## Text

Three steps, and every step clears 4.5:1 on every rung and fill it can land on.

| Token        | Use                          | Light    | Dark     |
| ------------ | ---------------------------- | -------- | -------- |
| `primary`    | a heading, a value, a name   | gray-900 | gray-50  |
| `foreground` | body                         | gray-700 | gray-300 |
| `muted`      | a label, a hint, a timestamp | gray-600 | gray-400 |

`muted` is a text colour. The fill it pairs with is `muted-background`.
`primary` is also the fill of the filled button, with `primary-foreground` as
its label. The filled button is deliberately the loudest thing on a light page:
it is the primary action, and the accent button is there for the case where the
app's colour should carry it instead.

The frame carries the same two steps: `sidebar-foreground` is `foreground` and
`sidebar-muted` is `muted`. Dark `sidebar-muted` used to be gray-500, which is
4.2:1 on the frame, and a 10px section label cannot afford that.

The tightest pairs, checked with WCAG on the oklch values:

| Pair                                 | Ratio |
| ------------------------------------ | ----- |
| light `muted` on `sidebar-accent`    | 4.63  |
| light `muted` on `secondary`         | 5.43  |
| light `muted` on `muted-background`  | 5.71  |
| dark `muted` on `input` / `popover`  | 4.82  |
| dark `muted` on `secondary`          | 5.20  |
| dark `sidebar-muted` on `sidebar`    | 5.86  |
| light `ring` on `sidebar` (non-text) | 3.70  |
| dark `ring` on `sidebar` (non-text)  | 8.84  |

## Accent

The accent is the one saturated colour: the primary action, the focus ring,
selection, the checked state. It moves three tokens, `accent`,
`accent-hover` and `ring`, and everything that is "the app's colour" reads
one of those.

Its label is `accent-foreground`, which is whichever of white and near-black
clears 4.5:1 on the fill. In light mode the fill is the lightest step of the
hue that carries white (violet and rose at 600, blue, emerald and cyan at
700), so the label is white. In dark mode the fill stays at 500 and the label
flips to gray-950: white on any 500 is about 3:1, and dulling the fill to fix
that is worse than flipping the label. Amber is the one hue white never sits
on, so its label is dark in both modes.

Hover goes darker in light mode and lighter in dark mode, because contrast
against the surface is what a hover has to keep. The dark ring is the 400,
because the 500 on gray-900 is a 3:1 ring and a ring that faint is the reason
focus is hard to see.

## Customising the real app

Everything above lives in three blocks of `app.css`:

- `@theme { ... }` is light mode and the defaults.
- `.dark { ... }` is dark mode.
- `html[data-accent='...']` is one block per accent, light and dark.

To retheme, change the palette rows at the top of `@theme` (the `--color-gray-*`
and `--color-blue-*` ramps) and leave the ladder alone: the rungs name palette
steps, so a new gray ramp moves every surface at once. To add an accent, add
its two blocks, add the name to `accentColors` in
`resources/js/composables/useAccentColor.ts`, and give it a swatch there. The
inline script in `resources/views/app.blade.php` reads the same storage key
before first paint, so nothing else changes.

Two things are worth rechecking after any change: the `theme-color` meta tags
in `app.blade.php` and the manifest colours in `vite.config.ts` are the hex of
the canvas rung in each mode (`#f3f3f6` light, `#16171b` dark), and the
contrast pairs in the tables above. A quick check is the WCAG ratio of
`foreground` and `muted` on each rung and fill, and of `accent-foreground` on
`accent` for all six accents.
