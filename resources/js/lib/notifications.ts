import type { RouteLocationRaw } from 'vue-router'

/**
 * How each notification type looks and where clicking it goes.
 *
 * Keyed by the same stable string the server stores in `type`, so a row
 * written a year ago still renders after the PHP class behind it has been
 * renamed. A type with no entry here falls back rather than breaking the
 * list: an inbox that cannot render one row must still render the rest.
 */
export interface NotificationPresentation {
  icon: string
  /** Paints the icon. `alert` is for things the reader may need to act on. */
  tone: 'default' | 'alert'
  to?: RouteLocationRaw
}

export const notificationPresentation: Record<string, NotificationPresentation> = {
  'security.alert': {
    icon: 'lucide:shield-alert',
    tone: 'alert',
    to: { name: 'account-security' },
  },
  'people.invite_accepted': {
    icon: 'lucide:user-round-check',
    tone: 'default',
    to: { name: 'users' },
  },
}

export const fallbackNotificationPresentation: NotificationPresentation = {
  icon: 'lucide:bell',
  tone: 'default',
}

export const presentationFor = (type: string): NotificationPresentation =>
  notificationPresentation[type] ?? fallbackNotificationPresentation

export const notificationChannelIcons: Record<string, string> = {
  push: 'lucide:monitor-smartphone',
  mail: 'lucide:mail',
  sms: 'lucide:message-square-text',
}

/**
 * Notification keys carry dots (`security.alert`) and vue-i18n reads a dot as
 * a level of nesting, so the message tree uses underscores and this is the
 * one place that knows it.
 */
export const messageKeyFor = (type: string): string => type.replace(/\./g, '_')
