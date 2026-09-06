import { useThrottleFn } from '@vueuse/core'
import {
  computed,
  inject,
  onScopeDispose,
  provide,
  ref,
  toValue,
  watch,
  type ComputedRef,
  type InjectionKey,
  type MaybeRefOrGetter,
} from 'vue'

import { usePresence, type PresenceMember, type UsePresenceReturn } from '~/composables/usePresence'

/** Someone else with this field open right now. */
export interface FieldEditor {
  member: PresenceMember
  /** They have changed it since focusing it. */
  dirty: boolean
  /**
   * What they have typed so far, only on a form that opted in with
   * `values: true`, and never for a secret field. Undefined otherwise.
   */
  value?: string
}

/**
 * What goes over the wire: who, which field, changed or not, and on an
 * opted-in form the value as typed. A whisper never reaches the server, so
 * a value here is a preview and nothing more.
 */
interface Claim {
  field: string | null
  dirty: boolean
  value?: string
}

interface FieldPresenceContext {
  editorsOf: (field: string) => FieldEditor[]
  claim: (field: string) => void
  release: (field: string) => void
  setDirty: (field: string, dirty: boolean, value?: string) => void
  /** Whether this form sends values for the field; false for a secret one. */
  sendsValue: (field: string) => boolean
}

export interface FieldPresenceOptions {
  /**
   * Whisper the value as it is typed, so the other person sees what is
   * coming, not only that something is. Off by default: a value on a
   * whisper is a different promise from a value in a mutation. It is
   * never sent for a `type="password"` control or a field in `secret`.
   */
  values?: boolean
  /** Field names whose value stays on this screen whatever `values` says. */
  secret?: readonly string[]
}

/**
 * The most of a value one whisper carries. Reverb drops frames over 10 KB
 * and a textarea can hold more; the head is what the other person reads.
 */
const VALUE_MAX_LENGTH = 1000

/** Absent unless a form provided one, which is how the feature stays off. */
const contextKey: InjectionKey<FieldPresenceContext> = Symbol('field-presence')

/**
 * A person may hold one field at a time, so one whisper carries the whole of
 * their state and a burst of them collapses to the last. 50 ms is the same
 * floor the cursor stream takes: well inside what Reverb accepts from one
 * connection, and faster than anyone reads a dot.
 */
const THROTTLE_MS = 50

const WHISPER = 'field'

/**
 * Turn on field-level presence for the form below this component.
 *
 * Every `FormField` inside it then says who has a field focused and whether
 * they have unsaved changes in it. It rides whispers, so nothing is stored
 * and nothing survives the sender's socket: a lock this is not.
 *
 * ```ts
 * provideFieldPresence('users')
 * provideFieldPresence('users', { values: true, secret: ['password'] })
 * ```
 *
 * With `values` the other person also sees what is being typed, under the
 * field, as a preview. Their own input is never written to.
 *
 * The resource is the presence channel, so it takes the ability that channel
 * takes. A form nobody else can open needs none of this.
 */
export function provideFieldPresence(
  resource: MaybeRefOrGetter<string | null>,
  options: FieldPresenceOptions = {}
): UsePresenceReturn {
  /** Sender id → the field they hold. Absent means they hold none. */
  const claims = ref(new Map<string, Claim>())
  const secret = new Set(options.secret ?? [])

  /** This session's own claim, which is not reactive: only the wire reads it. */
  let mine: Claim | null = null

  // Registered before the channel is joined, because a scope runs its
  // cleanups in registration order. The other way round the channel has
  // already been left and the release whisper goes nowhere, leaving a form
  // that closed still marked as being edited on every other screen.
  onScopeDispose(() => {
    if (!mine) return

    mine = null
    // Directly, not through the throttle: the scope is going away with it.
    flush()
  })

  const presence = usePresence(resource)

  const flush = (): void => {
    const claim: Claim = { field: mine?.field ?? null, dirty: mine?.dirty ?? false }

    if (mine?.value !== undefined) claim.value = mine.value

    presence.whisper(WHISPER, claim)
  }

  const push = useThrottleFn(flush, THROTTLE_MS, true)

  const write = (next: Claim | null): void => {
    mine = next
    void push()
  }

  const context: FieldPresenceContext = {
    editorsOf: (field) =>
      presence.others.value
        .map((member) => ({ member, claim: claims.value.get(member.id) }))
        .filter((entry): entry is { member: PresenceMember; claim: Claim } =>
          Boolean(entry.claim && entry.claim.field === field)
        )
        .map(({ member, claim }) =>
          claim.value === undefined
            ? { member, dirty: claim.dirty }
            : { member, dirty: claim.dirty, value: claim.value }
        ),
    claim: (field) => write({ field, dirty: false }),
    release: (field) => {
      if (mine?.field !== field) return

      write(null)
    },
    setDirty: (field, dirty, value) => {
      if (mine?.field !== field) return

      const next: Claim = { field, dirty }

      if (value !== undefined && context.sendsValue(field)) {
        next.value = value.slice(0, VALUE_MAX_LENGTH)
      }

      if (mine.dirty === next.dirty && mine.value === next.value) return

      write(next)
    },
    sendsValue: (field) => options.values === true && !secret.has(field),
  }

  if (presence.supported) {
    presence.onWhisper<Claim>(WHISPER, ({ senderId, field, dirty, value }) => {
      const next = new Map(claims.value)

      if (field) {
        next.set(
          senderId,
          typeof value === 'string'
            ? { field, dirty: dirty === true, value }
            : { field, dirty: dirty === true }
        )
      } else next.delete(senderId)

      claims.value = next
    })

    // A tab that closes never gets to release anything, so the roster is what
    // says they are gone. Without this the field keeps their avatar forever.
    watch(presence.members, (members) => {
      const present = new Set(members.map((member) => member.id))
      const stale = [...claims.value.keys()].filter((id) => !present.has(id))

      if (stale.length === 0) return

      claims.value = new Map([...claims.value].filter(([id]) => present.has(id)))
    })

    provide(contextKey, context)
  }

  return presence
}

/** Bound with `v-on` on the field wrapper, or empty when presence is off. */
export type FieldPresenceHandlers =
  | Record<string, never>
  | {
      focusin: (event: FocusEvent) => void
      focusout: (event: FocusEvent) => void
      input: (event: Event) => void
      change: (event: Event) => void
    }

export interface UseFieldPresenceReturn {
  /** True when a form above provided a channel and realtime is on. */
  active: boolean
  editors: ComputedRef<FieldEditor[]>
  handlers: FieldPresenceHandlers
  /**
   * For a control that changes its value without emitting a DOM event a
   * listener can see, a reka-ui select or combobox say. The caller knows it
   * changed; the wrapper does not.
   */
  setDirty: (dirty: boolean) => void
}

const valueOf = (target: EventTarget | null): string | null =>
  target instanceof HTMLInputElement ||
  target instanceof HTMLTextAreaElement ||
  target instanceof HTMLSelectElement
    ? target.value
    : null

/** A password never leaves the screen, whatever the form opted into. */
const isSecretControl = (target: EventTarget | null): boolean =>
  target instanceof HTMLInputElement && target.type === 'password'

/**
 * The field half of `provideFieldPresence`, which `FormField` calls for you.
 *
 * The handlers go on the wrapper rather than the control, because a field is
 * not one element: an OTP input is six, a combobox is a button and a listbox,
 * a date picker is whatever reka-ui renders today. `focusin` and `focusout`
 * bubble where `focus` and `blur` do not, so one pair on the wrapper covers
 * every control the kit puts inside a field, native or not, without the field
 * having to bind anything.
 *
 * Returns `{}` for handlers with no provider above, so an unconfigured field
 * attaches no listeners at all.
 */
export function useFieldPresence(
  field: MaybeRefOrGetter<string | null | undefined>
): UseFieldPresenceReturn {
  const context = inject(contextKey, null)
  const name = computed(() => toValue(field) ?? null)

  if (!context) {
    return { active: false, editors: computed(() => []), handlers: {}, setDirty: () => {} }
  }

  const editors = computed(() => (name.value ? context.editorsOf(name.value) : []))

  /** What the control held when it took focus, so "changed" is a comparison. */
  let baseline: string | null = null

  const focusin = (event: FocusEvent): void => {
    if (!name.value) return

    baseline = valueOf(event.target)
    context.claim(name.value)
  }

  const focusout = (event: FocusEvent): void => {
    if (!name.value) return

    // Stepping between two controls of the same field, an OTP group say, is
    // not a blur: the wrapper still holds the focus.
    const wrapper = event.currentTarget

    if (
      wrapper instanceof Node &&
      event.relatedTarget instanceof Node &&
      wrapper.contains(event.relatedTarget)
    ) {
      return
    }

    baseline = null
    context.release(name.value)
  }

  const changed = (event: Event): void => {
    if (!name.value) return

    const value = valueOf(event.target)
    const dirty = baseline === null || value === null ? true : value !== baseline

    // A control with no value of its own, a combobox or a date picker, has
    // nothing to compare against, so anything it emits counts as a change.
    context.setDirty(
      name.value,
      dirty,
      value !== null && !isSecretControl(event.target) ? value : undefined
    )
  }

  watch(name, (_next, previous) => {
    if (previous) context.release(previous)
  })

  onScopeDispose(() => {
    if (name.value) context.release(name.value)
  })

  return {
    active: true,
    editors,
    handlers: { focusin, focusout, input: changed, change: changed },
    setDirty: (dirty) => {
      if (name.value) context.setDirty(name.value, dirty)
    },
  }
}
