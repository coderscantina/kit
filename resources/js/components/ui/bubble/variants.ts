import type { VariantProps } from 'class-variance-authority'
import { cva } from 'class-variance-authority'

export const bubbleVariants = cva(
  'gap-1 data-[align=end]:self-end max-w-[80%] data-[variant=ghost]:max-w-full group-data-[align=end]/message:self-end group/bubble relative flex w-fit min-w-0 flex-col',
  {
    variants: {
      variant: {
        default:
          '*:data-[slot=bubble-content]:bg-primary *:data-[slot=bubble-content]:text-primary-foreground [&>[data-slot=bubble-content]:is(button,a):hover]:bg-primary/80',
        secondary:
          '*:data-[slot=bubble-content]:bg-secondary *:data-[slot=bubble-content]:text-secondary-foreground [&>[data-slot=bubble-content]:is(button,a):hover]:bg-[color-mix(in_oklch,var(--secondary),var(--foreground)_5%)]',
        muted:
          '*:data-[slot=bubble-content]:bg-muted-background [&>[data-slot=bubble-content]:is(button,a):hover]:bg-[color-mix(in_oklch,var(--muted-background),var(--foreground)_5%)]',
        accent:
          '*:data-[slot=bubble-content]:bg-accent *:data-[slot=bubble-content]:text-accent-foreground [&>[data-slot=bubble-content]:is(button,a):hover]:bg-accent-hover',
        ai: '*:data-[slot=bubble-content]:bg-ai-background/15 *:data-[slot=bubble-content]:text-foreground [&>[data-slot=bubble-content]:is(button,a):hover]:bg-ai-background/25',
        outline:
          '*:data-[slot=bubble-content]:bg-card *:data-[slot=bubble-content]:border-border [&>[data-slot=bubble-content]:is(button,a):hover]:bg-muted-background [&>[data-slot=bubble-content]:is(button,a):hover]:text-foreground dark:[&>[data-slot=bubble-content]:is(button,a):hover]:bg-input/30',
        ghost:
          '*:data-[slot=bubble-content]:rounded-none *:data-[slot=bubble-content]:bg-transparent *:data-[slot=bubble-content]:p-0 [&>[data-slot=bubble-content]:is(button,a):hover]:bg-muted-background [&>[data-slot=bubble-content]:is(button,a):hover]:text-foreground dark:[&>[data-slot=bubble-content]:is(button,a):hover]:bg-muted-background/50 border-none',
        destructive:
          '*:data-[slot=bubble-content]:bg-destructive-background/30 *:data-[slot=bubble-content]:text-destructive [&>[data-slot=bubble-content]:is(button,a):hover]:bg-destructive-background/45',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
)
export type BubbleVariants = VariantProps<typeof bubbleVariants>

export const bubbleReactionsVariants = cva(
  'rounded-full ring-3 ring-card bg-muted-background shrink-0 gap-1 px-1.5 py-0.5 has-[button]:p-0 text-sm absolute z-10 flex w-fit items-center justify-center',
  {
    variants: {
      side: {
        top: 'top-0 -translate-y-3/4',
        bottom: 'bottom-0 translate-y-3/4',
      },
      align: {
        start: 'left-3',
        end: 'right-3',
      },
    },
    defaultVariants: {
      side: 'bottom',
      align: 'end',
    },
  }
)
export type BubbleReactionsVariants = VariantProps<typeof bubbleReactionsVariants>
