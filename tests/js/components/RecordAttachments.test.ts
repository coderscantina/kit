import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'

import RecordAttachments from '~/components/records/RecordAttachments.vue'

import { mountWithQuery } from '../support/harness'

const file: App.Data.AttachmentData = {
  id: 'a1',
  name: 'contract.pdf',
  mimeType: 'application/pdf',
  size: 2048,
  width: null,
  height: null,
  url: '/api/attachments/a1',
  thumbnailUrl: null,
  uploadedBy: 'Ada',
  createdAt: new Date().toISOString(),
}

const mountFiles = (editable: boolean, files: App.Data.AttachmentData[] = [file]) =>
  mountWithQuery(
    defineComponent(() => () => h(RecordAttachments, { type: 'widgets', id: 'w1', editable })),
    { seed: [[['rq', 'attachments.list', { type: 'widgets', id: 'w1' }], files]] }
  )

let unmount: (() => void) | undefined

afterEach(() => {
  unmount?.()
  unmount = undefined
})

describe('RecordAttachments', () => {
  it('links each file to where the server serves it', () => {
    const { wrapper } = mountFiles(false)
    unmount = () => wrapper.unmount()

    const link = wrapper.find('a[href="/api/attachments/a1"]')
    expect(link.exists()).toBe(true)
    expect(link.attributes('aria-label')).toBe('Open contract.pdf')
    expect(wrapper.text()).toContain('contract.pdf')
  })

  it('offers uploads and removal only when the viewer may change the record', () => {
    const readOnly = mountFiles(false)
    expect(readOnly.wrapper.find('input[type="file"]').exists()).toBe(false)
    expect(readOnly.wrapper.find('[aria-label="Remove contract.pdf"]').exists()).toBe(false)
    readOnly.wrapper.unmount()

    const { wrapper } = mountFiles(true)
    unmount = () => wrapper.unmount()
    expect(wrapper.find('input[type="file"]').attributes('multiple')).toBeDefined()
    expect(wrapper.find('[aria-label="Remove contract.pdf"]').exists()).toBe(true)
  })

  it('says so when there are no files', () => {
    const { wrapper } = mountFiles(false, [])
    unmount = () => wrapper.unmount()

    expect(wrapper.text()).toContain('No files yet.')
  })
})
