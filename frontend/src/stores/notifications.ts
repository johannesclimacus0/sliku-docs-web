import { ref } from 'vue'
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { notificationService } from '@/services/notifications'

export const useNotificationsStore = defineStore('notifications', () => {
    const unreadCount = ref(0)
    const connected = ref(false)
    const revision = ref(0)
    let inFlight: Promise<void> | null = null
    let refreshAgain = false
    async function refresh() {
        const auth = useAuthStore()
        const userId = auth.user?.id
        if (userId === undefined) {
            unreadCount.value = 0
            return
        }
        if (inFlight) {
            refreshAgain = true
            return inFlight
        }
        inFlight = (async () => {
            try {
                const result = await auth.withToken(notificationService.count)
                if (auth.user?.id === userId) unreadCount.value = result.unreadCount
            } catch {
            } finally {
                inFlight = null
                if (refreshAgain) {
                    refreshAgain = false
                    void refresh()
                }
            }
        })()
        return inFlight
    }
    function changed() {
        revision.value++
        void refresh()
    }
    return { unreadCount, refresh, connected, revision, changed }
})
