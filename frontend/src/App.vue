<script setup lang="ts">
import AppSidebar from '@/components/AppSidebar.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { computed, ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter, RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import EmailVerificationBanner from '@/components/EmailVerificationBanner.vue'
import { useNotificationsStore } from '@/stores/notifications'
import { connectNotifications } from '@/services/realtime'
const auth = useAuthStore()
const notifications = useNotificationsStore()
const route = useRoute()
const router = useRouter()
const logoutError = ref('')
const leaving = ref(false)
const nav = computed(() => [
    { to: '/', label: 'Обзор', exact: true },
    { to: '/profile', label: 'Мой профиль' },
    { to: '/notifications', label: 'Уведомления' },
    ...(auth.user?.role === 'admin'
        ? [
              { to: '/users', label: 'Пользователи' },
              { to: '/operations', label: 'Инструменты' },
          ]
        : []),
])
let notificationTimer: ReturnType<typeof setInterval> | undefined
function refreshNotifications() {
    if (!document.hidden) void notifications.refresh()
}
watch(
    () => auth.user?.id,
    (userId, _, cleanup) => {
        notifications.unreadCount = 0
        notifications.connected = false
        void notifications.refresh()
        if (userId !== undefined) {
            const disconnect = connectNotifications(userId, notifications.changed, (connected) => {
                notifications.connected = connected
            })
            cleanup(disconnect)
        }
    },
    { immediate: true },
)
onMounted(() => {
    notificationTimer = setInterval(() => {
        if (!notifications.connected) refreshNotifications()
    }, 30_000)
    document.addEventListener('visibilitychange', refreshNotifications)
})
onBeforeUnmount(() => {
    clearInterval(notificationTimer)
    document.removeEventListener('visibilitychange', refreshNotifications)
})
async function logout() {
    leaving.value = true
    try {
        await auth.logout()
        await router.push('/login')
    } catch (e) {
        logoutError.value = e instanceof Error ? e.message : 'Ошибка выхода'
    } finally {
        leaving.value = false
    }
}
</script>
<template>
    <RouterView v-if="route.meta.authScreen" />
    <div v-else class="grid min-h-dvh grid-cols-[210px_minmax(0,1fr)] max-[680px]:block">
        <AppSidebar
            :user="auth.user"
            :nav="nav"
            :current-path="route.path"
            :unread-count="notifications.unreadCount"
            :leaving="leaving"
            @logout="logout" />
        <div class="min-w-0">
            <div
                class="mx-auto max-w-[1220px] px-[clamp(24px,5vw,64px)] pt-10 pb-16 max-[680px]:px-[18px] max-[680px]:pt-6 max-[680px]:pb-10">
                <AppNotice :message="logoutError" />
                <EmailVerificationBanner /><RouterView />
            </div>
        </div>
    </div>
</template>
