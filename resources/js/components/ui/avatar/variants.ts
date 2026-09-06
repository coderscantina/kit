import { cva, type VariantProps } from 'class-variance-authority'

import type { PulseDotVariants } from '~/components/ui/pulse-dot'

export const avatarVariants = cva(
  'bg-accent overflow-clip flex items-center justify-center text-primary',
  {
    variants: {
      size: {
        sm: 'size-4 rounded-md',
        default: 'size-8 rounded-xl',
        lg: 'size-12 rounded-2xl',
      },
    },
    defaultVariants: {
      size: 'default',
    },
  }
)

export type AvatarVariants = VariantProps<typeof avatarVariants>

/**
 * The three states a presence indicator has.
 *
 * `online` and `offline` follow from a roster. `unavailable` never does: only
 * the application knows whether it means away, in a meeting or do not
 * disturb, so it is always passed in.
 */
export type PresenceStatus = 'online' | 'offline' | 'unavailable'

/** Which dot each state draws, so colour is a token and not a decision. */
export const presenceDotVariants: Record<PresenceStatus, PulseDotVariants['variant']> = {
  online: 'success',
  offline: 'default',
  unavailable: 'destructive',
}
