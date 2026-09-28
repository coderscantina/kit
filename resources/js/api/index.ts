import { ApiClient } from '~/api/client'
import { AccountResource } from '~/api/resources/account'
import { AttachmentsResource } from '~/api/resources/attachments'
import { AuthResource } from '~/api/resources/auth'
import { InvitesResource } from '~/api/resources/invites'
import { NotificationsResource } from '~/api/resources/notifications'
import { PeopleResource } from '~/api/resources/people'
import { UsersResource } from '~/api/resources/users'
import { ViewsResource } from '~/api/resources/views'
import { runtimeConfig } from '~/lib/runtime-config'

const client = new ApiClient({ baseURL: runtimeConfig.apiBaseUrl })

/** The REST remainder. Feature data goes through useReactiveQuery/useReactiveMutation. */
export const api = {
  client,
  auth: new AuthResource(client),
  account: new AccountResource(client),
  attachments: new AttachmentsResource(client),
  invites: new InvitesResource(client),
  notifications: new NotificationsResource(client),
  people: new PeopleResource(client),
  users: new UsersResource(client),
  views: new ViewsResource(client),
  // kit:resources
}
