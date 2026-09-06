import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

import type { DownloadedFile } from '~/api/client'
import TableExport from '~/components/ui/data-table/TableExport.vue'
import type { ExportFormat } from '~/components/ui/data-table/types'

const { saveFile } = vi.hoisted(() => ({ saveFile: vi.fn() }))
vi.mock('~/lib/download', () => ({ saveFile }))

const { toast } = vi.hoisted(() => ({
  toast: { warning: vi.fn(), error: vi.fn(), success: vi.fn() },
}))
vi.mock('vue-sonner', () => ({ toast }))

const file = (truncated = false): DownloadedFile => ({
  blob: new Blob(['a,b']),
  filename: 'people-2026-09-06.csv',
  truncated,
})

const exporter = (download: (format: ExportFormat) => Promise<DownloadedFile>) =>
  mount(TableExport, { attachTo: document.body, props: { download } })

/** The menu is portalled, so it is queried on the document, not the wrapper. */
const menuItems = () => [...document.querySelectorAll<HTMLElement>('[role="menuitem"]')]

const openMenu = async (wrapper: ReturnType<typeof exporter>) => {
  await wrapper.get('button').trigger('click')
  await nextTick()
  // The popper places itself asynchronously; until it has, its wrapper is
  // parked off screen and asserting on the position would be a false green.
  await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('TableExport', () => {
  it('is an icon with an accessible name, not a labelled button', () => {
    const wrapper = exporter(() => Promise.resolve(file()))
    const trigger = wrapper.get('button')

    expect(trigger.attributes('aria-label')).toBe('Export')
    expect(trigger.text()).toBe('')
  })

  it('offers every format and hands the picked one to the caller', async () => {
    const download = vi.fn(() => Promise.resolve(file()))
    const wrapper = exporter(download)

    await openMenu(wrapper)

    expect(menuItems().map((item) => item.textContent?.trim())).toEqual([
      'CSV',
      'Excel workbook',
      'PDF',
    ])

    // Anchored to the button rather than parked off screen: the tooltip and
    // the menu each own a popper, and the inner trigger takes the outer one's
    // root if the two are nested the wrong way round.
    expect(document.querySelector('[style*="-200%"]')).toBeNull()

    menuItems()[1]?.click()
    await nextTick()
    await nextTick()

    expect(download).toHaveBeenCalledWith('xlsx')
    expect(saveFile).toHaveBeenCalledWith(expect.objectContaining({ filename: expect.any(String) }))
  })

  it('warns when the server capped the rows, because the file is not the list', async () => {
    const wrapper = exporter(() => Promise.resolve(file(true)))

    await openMenu(wrapper)
    menuItems()[0]?.click()
    await nextTick()
    await nextTick()

    expect(toast.warning).toHaveBeenCalled()
  })

  it('reports a refused export instead of downloading nothing', async () => {
    saveFile.mockClear()
    const wrapper = exporter(() => Promise.reject(new Error('Forbidden')))

    await openMenu(wrapper)
    menuItems()[0]?.click()
    await nextTick()
    await nextTick()

    expect(saveFile).not.toHaveBeenCalled()
    expect(toast.error).toHaveBeenCalled()
  })
})
