import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

import { useConfirm } from '~/composables/useConfirm'

const flush = async () => {
  await nextTick()
  await vi.runAllTimersAsync()
}

describe('useConfirm', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  it('resolves true when the dialog is confirmed and false when it is dismissed', async () => {
    const { confirm, answer, dismiss } = useConfirm()

    const confirmed = confirm({ message: 'Delete?' })
    await nextTick()
    answer(true)
    await expect(confirmed).resolves.toBe(true)

    await flush()

    const dismissed = confirm({ message: 'Delete?' })
    await nextTick()
    // What Escape and an overlay click reach: without it the await hangs forever.
    dismiss()
    await expect(dismissed).resolves.toBe(false)

    await flush()
  })

  it('queues a second request instead of tearing down the first one', async () => {
    const { confirm, current, answer, dismiss } = useConfirm()

    const first = confirm({ message: 'First' })
    await nextTick()

    const second = confirm({ message: 'Second' })
    expect(current.value?.options.message).toBe('First')

    answer(true)
    await expect(first).resolves.toBe(true)

    await flush()
    expect(current.value?.options.message).toBe('Second')

    dismiss()
    await expect(second).resolves.toBe(false)

    await flush()
    expect(current.value).toBeNull()
  })

  it('lets the button win when the primitive closes itself first', async () => {
    const { confirm, answer, dismiss } = useConfirm()

    const result = confirm({ message: 'Delete?' })
    await nextTick()

    // The order reka produces for an AlertDialogAction: it closes the dialog,
    // and only then does the button's own click handler run.
    dismiss()
    answer(true)

    await expect(result).resolves.toBe(true)

    await flush()
  })
})
