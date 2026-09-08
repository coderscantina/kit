import { BaseResource } from '~/api/resources/base-resource'

/**
 * The settings half of notifications: which types reach this account on
 * which channels, and the phone the SMS channel depends on.
 *
 * The inbox itself is not here. It is a live list and goes through
 * `useReactiveQuery('notifications.list')`; a controller in front of it would
 * be a second way of asking the same question.
 */
export class NotificationsResource extends BaseResource {
  protected basePath = '/api/account'

  settings(): Promise<App.Data.NotificationSettingsData> {
    return this.client.get(`${this.basePath}/notifications`)
  }

  /** The whole matrix, as a map of notification key to enabled channels. */
  saveSettings(preferences: Record<string, string[]>): Promise<App.Data.NotificationSettingsData> {
    return this.client.put(`${this.basePath}/notifications`, { preferences })
  }

  /** Sends, or resends, the code. The number does not move onto the account yet. */
  requestPhoneCode(phone: string): Promise<App.Data.PhoneData> {
    return this.client.post(`${this.basePath}/phone`, { phone })
  }

  verifyPhone(code: string): Promise<App.Data.PhoneData> {
    return this.client.post(`${this.basePath}/phone/verify`, { code })
  }

  removePhone(): Promise<App.Data.PhoneData> {
    return this.client.delete(`${this.basePath}/phone`)
  }
}
