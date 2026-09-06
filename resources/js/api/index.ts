import { ApiClient } from '~/api/client'
import { AccountResource } from '~/api/resources/account'
import { AuthResource } from '~/api/resources/auth'
import { InvitesResource } from '~/api/resources/invites'
import { PeopleResource } from '~/api/resources/people'
import { UsersResource } from '~/api/resources/users'
import { runtimeConfig } from '~/lib/runtime-config'

const client = new ApiClient({ baseURL: runtimeConfig.apiBaseUrl })

/** The REST remainder. Feature data goes through useReactiveQuery/useReactiveMutation. */
export const api = {
  client,
  auth: new AuthResource(client),
  account: new AccountResource(client),
  invites: new InvitesResource(client),
  people: new PeopleResource(client),
  users: new UsersResource(client),
}
