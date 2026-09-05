import { BaseResource } from '~/api/resources/base-resource'
import type { Paginated } from '~/types/api'

export interface AcceptInvitePayload {
  token: string
  name?: string
  password?: string
  password_confirmation?: string
}

export class InvitesResource extends BaseResource {
  protected basePath = '/api/invites'

  index(page = 1): Promise<Paginated<App.Data.InviteData>> {
    return this.client.get(this.basePath, { page })
  }

  create(payload: { email: string; role: string }): Promise<App.Data.InviteData> {
    return this.client.post(this.basePath, payload)
  }

  destroy(id: string): Promise<void> {
    return this.client.delete(this.idPath(id))
  }

  show(id: string, token: string): Promise<App.Data.PublicInviteData> {
    return this.client.get(this.idPath(id), { token })
  }

  accept(id: string, payload: AcceptInvitePayload): Promise<{ user: App.Data.UserData }> {
    return this.client.post(this.idPath(id, '/accept'), payload)
  }

  decline(id: string, token: string): Promise<void> {
    return this.client.post(this.idPath(id, '/decline'), { token })
  }
}
