import { BaseResource } from '~/api/resources/base-resource'

/** Files on records. The list itself is the `attachments.list` reactive query. */
export class AttachmentsResource extends BaseResource {
  protected basePath = '/api/attachments'

  upload(
    record: App.Data.RecordArgs,
    file: File,
    options: { onProgress?: (fraction: number) => void; signal?: AbortSignal } = {}
  ): Promise<App.Data.AttachmentData> {
    const body = new FormData()
    body.append('type', record.type)
    body.append('id', record.id)
    body.append('file', file, file.name)

    return this.client.upload(this.basePath, body, options)
  }

  remove(id: string): Promise<void> {
    return this.client.delete(this.idPath(id))
  }
}
