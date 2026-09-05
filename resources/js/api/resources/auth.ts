import { BaseResource } from '~/api/resources/base-resource'

export interface LoginPayload {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterPayload {
  name: string
  email: string
  password: string
  password_confirmation: string
  locale?: string
}

export interface ResetPasswordPayload {
  token: string
  email: string
  password: string
  password_confirmation: string
}

export class AuthResource extends BaseResource {
  protected basePath = '/auth'

  me(): Promise<App.Data.MeData> {
    return this.client.get(`${this.basePath}/me`)
  }

  login(payload: LoginPayload, totpCode?: string): Promise<{ message: string }> {
    return this.client.post(`${this.basePath}/login`, payload, {
      headers: totpCode ? { 'X-TOTP-Code': totpCode } : {},
    })
  }

  logout(): Promise<void> {
    return this.client.post(`${this.basePath}/logout`)
  }

  register(payload: RegisterPayload): Promise<{ user: App.Data.UserData }> {
    return this.client.post(`${this.basePath}/register`, payload)
  }

  forgotPassword(email: string): Promise<{ message: string }> {
    return this.client.post(`${this.basePath}/password/forgot`, { email })
  }

  resetPassword(payload: ResetPasswordPayload): Promise<{ message: string }> {
    return this.client.post(`${this.basePath}/password/reset`, payload)
  }

  confirmPassword(password: string): Promise<{ message: string }> {
    return this.client.post(`${this.basePath}/password/confirm`, { password })
  }

  resendVerification(): Promise<{ message: string }> {
    return this.client.post(`${this.basePath}/email/resend`)
  }

  twoFactorSetup(): Promise<{ secret: string; url: string }> {
    return this.client.post(`${this.basePath}/2fa/setup`)
  }

  twoFactorConfirm(code: string): Promise<{ backup_codes: string[] }> {
    return this.client.post(`${this.basePath}/2fa/confirm`, { code })
  }

  twoFactorDisable(): Promise<{ message: string }> {
    return this.client.post(`${this.basePath}/2fa/disable`)
  }

  twoFactorBackupCodes(): Promise<{ backup_codes: string[] }> {
    return this.client.post(`${this.basePath}/2fa/backup-codes`)
  }

  impersonate(userId: string): Promise<App.Data.MeData> {
    return this.client.post(`${this.basePath}/impersonate`, { userId })
  }

  stopImpersonating(): Promise<App.Data.MeData> {
    return this.client.delete(`${this.basePath}/impersonate`)
  }
}
