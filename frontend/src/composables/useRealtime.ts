import { onScopeDispose } from 'vue'
import { realtime, type RealtimeEvent } from '@/services/realtime'

export function useRealtime<T = Record<string, unknown>>(event: string, callback: (data: T, message: RealtimeEvent) => void) {
    const stop = realtime.on<T>(event, callback)
    onScopeDispose(stop)
    return stop
}
