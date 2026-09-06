import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref, type Component } from 'vue'

import { channels, member, resetEcho, setRealtime } from '../support/echo'

vi.mock('~/lib/reactive', async () => ({
  echo: (await import('../support/echo')).fakeEcho,
  reactive: {},
}))

vi.mock('~/composables/useAuth', () => ({
  useAuth: () => ({ user: ref({ id: 'me', name: 'Me' }) }),
}))

const { provideFieldPresence } = await import('~/composables/useFieldPresence')
const FormField = (await import('~/components/ui/form/FormField.vue')).default

/** A field whose control is a component, not an element with a value. */
const Combobox = defineComponent({
  setup: () => () => h('div', { tabindex: 0, class: 'combobox' }, 'pick one'),
})

type FieldPresenceOptions = Parameters<typeof provideFieldPresence>[1]

const form = (
  control: Component | null = null,
  dirty = ref(false),
  options: FieldPresenceOptions = {},
  inputType = 'text'
) =>
  mount(
    defineComponent({
      setup() {
        provideFieldPresence('users', options)

        return () =>
          h(FormField, { name: 'email', label: 'Email', dirty: dirty.value }, () => [
            control ? h(control) : h('input', { id: 'email', type: inputType }),
          ])
      },
    })
  )

const fire = (element: Element, type: string, init: EventInit = {}) =>
  element.dispatchEvent(
    type.startsWith('focus')
      ? new FocusEvent(type, { bubbles: true, ...init })
      : new Event(type, { bubbles: true, ...init })
  )

const whispers = () => channels[0]?.whispers ?? []

describe('field presence', () => {
  beforeEach(() => {
    resetEcho()
    vi.useFakeTimers()
  })

  afterEach(() => vi.useRealTimers())

  it('sends who, which field and dirty, and never the value', async () => {
    const wrapper = form()
    const input = wrapper.find('input').element

    fire(input, 'focusin')
    await vi.advanceTimersByTimeAsync(60)

    expect(whispers()).toEqual([['field', { field: 'email', dirty: false, senderId: 'me' }]])

    input.value = 'ada@kit.test'
    fire(input, 'input')
    await vi.advanceTimersByTimeAsync(60)

    expect(whispers().at(-1)).toEqual(['field', { field: 'email', dirty: true, senderId: 'me' }])
    expect(JSON.stringify(whispers())).not.toContain('ada@kit.test')

    fire(input, 'focusout')
    await vi.advanceTimersByTimeAsync(60)

    expect(whispers().at(-1)).toEqual(['field', { field: null, dirty: false, senderId: 'me' }])

    wrapper.unmount()
  })

  it('carries the value only on a form that opted in, and shows the other side what is typed', async () => {
    const wrapper = form(null, ref(false), { values: true })
    const input = wrapper.find('input').element

    fire(input, 'focusin')
    await vi.advanceTimersByTimeAsync(60)

    input.value = 'ada@'
    fire(input, 'input')
    await vi.advanceTimersByTimeAsync(60)

    expect(whispers().at(-1)).toEqual([
      'field',
      { field: 'email', dirty: true, value: 'ada@', senderId: 'me' },
    ])

    // Typing on with the same dirty state is still worth a whisper: the value moved.
    input.value = 'ada@kit'
    fire(input, 'input')
    await vi.advanceTimersByTimeAsync(60)

    expect(whispers().at(-1)?.[1]).toMatchObject({ value: 'ada@kit' })

    channels[0]?.here([member('me', 'Me'), member('grace', 'Grace')])
    channels[0]?.emitWhisper('field', {
      senderId: 'grace',
      field: 'email',
      dirty: true,
      value: 'grace@',
    })
    await wrapper.vm.$nextTick()

    expect(wrapper.text()).toContain('Grace: grace@')
    // The preview is next to the input, never in it.
    expect(input.value).toBe('ada@kit')

    wrapper.unmount()
  })

  it('never sends a password or a field the form marked secret, and caps a long value', async () => {
    const password = form(null, ref(false), { values: true }, 'password')
    const input = password.find('input').element

    fire(input, 'focusin')
    input.value = 'hunter2'
    fire(input, 'input')
    await vi.advanceTimersByTimeAsync(60)

    expect(JSON.stringify(whispers())).not.toContain('hunter2')
    expect(whispers().at(-1)).toEqual(['field', { field: 'email', dirty: true, senderId: 'me' }])
    password.unmount()
    resetEcho()

    const secret = form(null, ref(false), { values: true, secret: ['email'] })
    const secretInput = secret.find('input').element

    fire(secretInput, 'focusin')
    secretInput.value = 'private'
    fire(secretInput, 'input')
    await vi.advanceTimersByTimeAsync(60)

    expect(JSON.stringify(whispers())).not.toContain('private')
    secret.unmount()
    resetEcho()

    const long = form(null, ref(false), { values: true })
    const longInput = long.find('input').element

    fire(longInput, 'focusin')
    longInput.value = 'x'.repeat(5000)
    fire(longInput, 'input')
    await vi.advanceTimersByTimeAsync(60)

    const last = whispers().at(-1)?.[1] as { value?: string } | undefined

    expect(last?.value).toHaveLength(1000)
    long.unmount()
  })

  it('collapses a burst of changes into one whisper per throttle window', async () => {
    const wrapper = form()
    const input = wrapper.find('input').element

    fire(input, 'focusin')

    for (const value of ['a', 'ab', 'abc', 'abcd']) {
      input.value = value
      fire(input, 'input')
    }

    await vi.advanceTimersByTimeAsync(60)

    // Focus led, the first change trailed, and typing on says nothing new.
    expect(whispers()).toHaveLength(2)
    expect(whispers().at(-1)).toEqual(['field', { field: 'email', dirty: true, senderId: 'me' }])

    wrapper.unmount()
  })

  it('releases the field on blur, on unmount and when the member leaves', async () => {
    const wrapper = form()

    channels[0]?.here([member('me', 'Me'), member('ada', 'Ada')])
    channels[0]?.emitWhisper('field', { senderId: 'ada', field: 'email', dirty: true })
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.sr-only').text()).toBe('Ada has unsaved changes here')

    channels[0]?.emitWhisper('field', { senderId: 'ada', field: null, dirty: false })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('.sr-only').exists()).toBe(false)

    channels[0]?.emitWhisper('field', { senderId: 'ada', field: 'email', dirty: false })
    channels[0]?.leaving(member('ada', 'Ada'))
    await wrapper.vm.$nextTick()

    expect(wrapper.find('.sr-only').exists()).toBe(false)

    // A form that unmounts while focused releases on the way out, before the
    // channel it would have released on is left.
    fire(wrapper.find('input').element, 'focusin')
    await vi.advanceTimersByTimeAsync(60)
    wrapper.unmount()

    expect(whispers().at(-1)).toEqual(['field', { field: null, dirty: false, senderId: 'me' }])
  })

  it('claims the field for a control that is a component, not an input', async () => {
    const wrapper = form(Combobox)

    fire(wrapper.find('.combobox').element, 'focusin')
    await vi.advanceTimersByTimeAsync(60)

    expect(whispers().at(-1)).toEqual(['field', { field: 'email', dirty: false, senderId: 'me' }])

    // Nothing to compare a value against, so anything it emits is a change.
    fire(wrapper.find('.combobox').element, 'change')
    await vi.advanceTimersByTimeAsync(60)

    expect(whispers().at(-1)).toEqual(['field', { field: 'email', dirty: true, senderId: 'me' }])

    wrapper.unmount()
  })

  it('takes dirty from the field when the control emits nothing at all', async () => {
    const dirty = ref(false)
    const wrapper = form(Combobox, dirty)

    fire(wrapper.find('.combobox').element, 'focusin')
    await vi.advanceTimersByTimeAsync(60)

    dirty.value = true
    await wrapper.vm.$nextTick()
    await vi.advanceTimersByTimeAsync(60)

    expect(whispers().at(-1)).toEqual(['field', { field: 'email', dirty: true, senderId: 'me' }])

    wrapper.unmount()
  })

  it('does nothing with realtime off', async () => {
    setRealtime(false)

    const wrapper = form()

    fire(wrapper.find('input').element, 'focusin')
    await vi.advanceTimersByTimeAsync(60)

    expect(channels).toHaveLength(0)
    expect(wrapper.find('.sr-only').exists()).toBe(false)

    wrapper.unmount()
  })
})
