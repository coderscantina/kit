import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { computed, nextTick, ref } from 'vue'

import SavedViews from '~/components/ui/data-table/SavedViews.vue'
import type { UseSavedViews } from '~/composables/useSavedViews'

const view = (id: string, name: string): App.Data.SavedViewData => ({
  id,
  scope: 'people',
  name,
  params: { status: 'pending' },
  isDefault: false,
  createdAt: '2026-01-01T00:00:00+00:00',
})

const savedViews = (current: App.Data.SavedViewData | null): UseSavedViews => ({
  views: computed(() => [view('v1', 'Waiting on a reply')]),
  isLoading: ref(false),
  current: computed(() => current),
  isSaving: ref(false),
  save: vi.fn(),
  remove: vi.fn(),
  setDefault: vi.fn(),
  apply: vi.fn(),
})

const views = (current: App.Data.SavedViewData | null = null) =>
  mount(SavedViews, {
    attachTo: document.body,
    props: { views: savedViews(current), dirty: false },
    global: {
      stubs: { Icon: { props: ['name'], template: '<i :data-icon="name" />' } },
    },
  })

const trigger = (current: App.Data.SavedViewData | null = null) => views(current).get('button')

describe('SavedViews', () => {
  it('is the bookmark alone, with the menu name as its accessible name', () => {
    const button = trigger()

    expect(button.text()).toBe('')
    expect(button.attributes('aria-label')).toBe('Saved views')
    expect(button.get('i').attributes('data-icon')).toBe('lucide:bookmark')
  })

  it('names the applied view and marks it with the icon, not only with colour', () => {
    const button = trigger(view('v1', 'Waiting on a reply'))

    expect(button.attributes('aria-label')).toBe('Saved view: Waiting on a reply')
    expect(button.get('i').attributes('data-icon')).toBe('lucide:bookmark-check')
  })

  it('opens its menu, tooltip and all', async () => {
    const wrapper = views()

    await wrapper.get('button').trigger('click')
    await nextTick()
    // The popper places itself asynchronously; until it has, its wrapper is
    // parked off screen and asserting on the position would be a false green.
    await new Promise((resolve) => setTimeout(resolve, 0))

    // The menu is portalled, so it is queried on the document.
    expect(document.querySelector('[role="menu"]')).not.toBeNull()
    expect(
      [...document.querySelectorAll('[role="menuitem"]')].map((item) => item.textContent?.trim())
    ).toContain('Waiting on a reply')

    // The tooltip and the menu each own a popper. Nest their triggers the
    // wrong way round and the inner one anchors to the outer one's root, so
    // the menu opens parked off screen instead of under the button.
    expect(document.querySelector('[style*="-200%"]')).toBeNull()
  })
})
