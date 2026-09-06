import { BaseResource } from '~/api/resources/base-resource'
import type { Paginated } from '~/types/api'

export class AccountResource extends BaseResource {
  protected basePath = '/api/account'

  updateProfile(payload: { name?: string; locale?: string }): Promise<App.Data.UserData> {
    return this.client.patch(`${this.basePath}/profile`, payload)
  }

  /**
   * Asks for the address change. The address does not move until the link
   * mailed to it is opened, so the user that comes back has `pendingEmail`
   * set, not a new `email`.
   */
  requestEmailChange(email: string): Promise<{ message: string; user: App.Data.UserData }> {
    return this.client.post(`${this.basePath}/email`, { email })
  }

  cancelEmailChange(): Promise<App.Data.UserData> {
    return this.client.delete(`${this.basePath}/email`)
  }

  /** Token-gated and reachable without a session: the link is opened wherever the mail is. */
  confirmEmailChange(id: string, token: string): Promise<void> {
    return this.client.post(`/api/account/email/confirm/${encodeURIComponent(id)}`, { token })
  }

  uploadAvatar(file: Blob): Promise<App.Data.UserData> {
    const body = new FormData()
    body.append('avatar', file, 'avatar.png')

    return this.client.post(`${this.basePath}/avatar`, body)
  }

  removeAvatar(): Promise<App.Data.UserData> {
    return this.client.delete(`${this.basePath}/avatar`)
  }

  sessions(): Promise<App.Data.SessionData[]> {
    return this.client.get(`${this.basePath}/sessions`)
  }

  revokeSession(id: string): Promise<{ revoked: number }> {
    return this.client.delete(`${this.basePath}/sessions/${encodeURIComponent(id)}`)
  }

  revokeOtherSessions(): Promise<{ revoked: number }> {
    return this.client.delete(`${this.basePath}/sessions`)
  }

  securityActivity(page = 1): Promise<Paginated<App.Data.SecurityEventData>> {
    return this.client.get(`${this.basePath}/security-activity`, { page })
  }

  /** The export URL, opened as a download rather than fetched into memory. */
  exportUrl(): string {
    return `${this.basePath}/export`
  }

  export(): Promise<Record<string, unknown>> {
    return this.client.get(`${this.basePath}/export`)
  }

  updatePassword(payload: {
    current_password: string
    password: string
    password_confirmation: string
  }): Promise<{ message: string }> {
    return this.client.put(`${this.basePath}/password`, payload)
  }

  destroy(): Promise<void> {
    return this.client.delete(this.basePath)
  }
}
