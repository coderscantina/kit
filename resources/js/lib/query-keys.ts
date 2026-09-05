/** Query keys for the REST remainder. Reactive queries key themselves as `['rq', name, args]`. */
export const queryKeys = {
  me: () => ['auth', 'me'] as const,
  users: (page: number) => ['users', 'list', page] as const,
  roles: () => ['roles'] as const,
  invites: (page: number) => ['invites', 'list', page] as const,
  publicInvite: (id: string, token: string) => ['invites', 'public', id, token] as const,
}
