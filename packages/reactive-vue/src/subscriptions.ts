import type { EchoChannelLike, EchoLike, ReactiveTransport } from './types'

export interface ActiveSubscription {
  id: string
  channelName: string
  channel: EchoChannelLike | null
  name: string
  args: unknown
  mutationId: number
}

/**
 * One server subscription per query key, however many components mount
 * it. The manager owns the Echo channel and the unsubscribe, and always
 * leaves a channel by the name it joined with, never by a reactive value
 * that may already point at the next key.
 */
export class SubscriptionManager {
  private readonly active = new Map<string, ActiveSubscription>()

  private readonly refs = new Map<string, number>()

  constructor(
    private readonly transport: ReactiveTransport,
    private readonly echo: EchoLike | null
  ) {}

  get(key: string): ActiveSubscription | undefined {
    return this.active.get(key)
  }

  has(key: string): boolean {
    return this.active.has(key)
  }

  keys(): string[] {
    return [...this.active.keys()]
  }

  /** Replace whatever subscription the key had with a fresh one. */
  register(
    key: string,
    subscription: Omit<ActiveSubscription, 'channel' | 'channelName'>
  ): ActiveSubscription {
    this.release(key)

    const channelName = `subscription.${subscription.id}`
    const entry: ActiveSubscription = {
      ...subscription,
      channelName,
      channel: this.echo?.private(channelName) ?? null,
    }
    this.active.set(key, entry)

    return entry
  }

  retain(key: string): void {
    this.refs.set(key, (this.refs.get(key) ?? 0) + 1)
  }

  /** Drop a reference; the last one releases the server subscription. */
  dispose(key: string): void {
    const count = (this.refs.get(key) ?? 1) - 1

    if (count > 0) {
      this.refs.set(key, count)
      return
    }

    this.refs.delete(key)
    this.release(key)
  }

  release(key: string): void {
    const entry = this.active.get(key)

    if (!entry) {
      return
    }

    this.active.delete(key)
    entry.channel?.stopListening('.ResultChanged')
    entry.channel?.stopListening('.SubscriptionRevoked')
    this.echo?.leave(entry.channelName)
    void this.transport.unsubscribe(entry.id).catch(() => {
      // The registry expires it within the hour either way.
    })
  }

  releaseAll(): void {
    for (const key of this.keys()) {
      this.release(key)
    }
  }
}
