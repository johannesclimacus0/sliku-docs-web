import { Centrifuge } from 'centrifuge'
import { request } from './http'
import { useAuthStore } from '@/stores/auth'

type Connection = { enabled: false } | { enabled: true; token: string; url: string; channel: string }

export type RealtimeEvent = { id: string; event: string; data: Record<string, unknown> }
type Listener = (event: RealtimeEvent) => void
const listeners = new Map<string, Set<Listener>>()
const connectionListeners = new Set<() => void>()

export const realtime = {
    on<T = Record<string, unknown>>(name: string, callback: (data: T, event: RealtimeEvent) => void) {
        const listener: Listener = (event) => callback(event.data as T, event)
        const group = listeners.get(name) ?? new Set<Listener>()
        group.add(listener)
        listeners.set(name, group)
        return () => {
            group.delete(listener)
            if (group.size === 0) listeners.delete(name)
        }
    },
    onReconnect(callback: () => void) {
        connectionListeners.add(callback)
        return () => {
            connectionListeners.delete(callback)
        }
    },
}

export function connectRealtime(userId: number, status: (connected: boolean) => void) {
    const auth = useAuthStore()
    let active = true
    let client: Centrifuge | null = null
    let retry: ReturnType<typeof setTimeout> | undefined
    async function credentials() {
        if (!active || auth.user?.id !== userId) throw new Error('Connection cancelled')
        const result = await auth.withToken((token) =>
            request<Connection>({
                url: '/broadcast/connection',
                headers: { Authorization: `Bearer ${token}` },
            }),
        )
        if (!active || auth.user?.id !== userId) throw new Error('Connection cancelled')
        return result
    }
    async function start() {
        try {
            const config = await credentials()
            if (!config.enabled) return
            client = new Centrifuge(config.url, {
                token: config.token,
                getToken: async () => {
                    const next = await credentials()
                    if (!next.enabled) throw new Error('Realtime disabled')
                    return next.token
                },
            })
            client.on('error', () => {
                /* Connection recovery is managed by the SDK. */
            })
            client.on('connected', () => {
                if (active) {
                    status(true)
                    for (const callback of connectionListeners) callback()
                }
            })
            client.on('connecting', () => {
                if (active) status(false)
            })
            client.on('disconnected', () => {
                if (active) status(false)
            })
            client.on('publication', (context) => {
                if (!active || context.channel !== config.channel) return
                const message = context.data
                if (
                    !message ||
                    typeof message.event !== 'string' ||
                    typeof message.id !== 'string' ||
                    !message.data ||
                    typeof message.data !== 'object' ||
                    Array.isArray(message.data)
                )
                    return
                for (const callback of listeners.get(message.event) ?? []) callback(message as RealtimeEvent)
            })
            client.connect()
        } catch {
            if (active) {
                status(false)
                retry = setTimeout(() => {
                    void start()
                }, 15_000)
            }
        }
    }
    void start()
    return () => {
        active = false
        clearTimeout(retry)
        client?.disconnect()
        status(false)
    }
}

export function connectNotifications(userId: number, changed: () => void, status: (connected: boolean) => void) {
    const offCreated = realtime.on('notification.created', changed)
    const offRead = realtime.on('notification.read', changed)
    const offConnected = realtime.onReconnect(changed)
    const disconnect = connectRealtime(userId, status)
    return () => {
        disconnect()
        offCreated()
        offRead()
        offConnected()
    }
}
