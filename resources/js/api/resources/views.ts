import { BaseResource } from '~/api/resources/base-resource'

export interface SaveViewPayload {
  scope: string
  name: string
  params: Record<string, string>
  isDefault?: boolean
}

/**
 * The signed-in user's saved list states. Private by definition, so there is
 * no owner to pass: the server scopes every call to the session.
 */
export class ViewsResource extends BaseResource {
  protected basePath = '/api/account/views'

  index(scope: string): Promise<App.Data.SavedViewData[]> {
    return this.client.get(this.basePath, { scope })
  }

  store(payload: SaveViewPayload): Promise<App.Data.SavedViewData> {
    return this.client.post(this.basePath, payload)
  }

  update(
    id: string,
    payload: Partial<Omit<SaveViewPayload, 'scope'>>
  ): Promise<App.Data.SavedViewData> {
    return this.client.patch(this.idPath(id), payload)
  }

  destroy(id: string): Promise<void> {
    return this.client.delete(this.idPath(id))
  }
}
