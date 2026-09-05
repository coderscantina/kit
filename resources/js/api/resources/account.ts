import { BaseResource } from '~/api/resources/base-resource'

export class AccountResource extends BaseResource {
  protected basePath = '/api/account'

  updateProfile(payload: { name?: string; locale?: string }): Promise<App.Data.UserData> {
    return this.client.patch(`${this.basePath}/profile`, payload)
  }

  updateEmail(email: string): Promise<App.Data.UserData> {
    return this.client.patch(`${this.basePath}/email`, { email })
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
