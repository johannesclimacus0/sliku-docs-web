<script setup lang="ts">
import NotificationItem from '@/components/NotificationItem.vue'
import AppPagination from '@/components/ui/AppPagination.vue'
import PageHeading from '@/components/ui/PageHeading.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useNotificationsStore } from '@/stores/notifications'
import { notificationService, type NotificationList } from '@/services/notifications'

const auth = useAuthStore()
const notifications = useNotificationsStore()
const data = ref<NotificationList | null>(null)
const page = ref(1)
const unreadOnly = ref(false)
const busy = ref(false)
const error = ref('')
const message = ref('')
let requestVersion = 0
let timer: ReturnType<typeof setInterval> | undefined
const pages = computed(() => Math.max(1, Math.ceil((data.value?.pagination.total ?? 0) / (data.value?.pagination.perPage ?? 20))))
async function load() {
    const version = ++requestVersion
    try {
        const result = await auth.withToken((token) => notificationService.list(token, page.value, unreadOnly.value))
        if (version !== requestVersion) return
        if (page.value > 1 && result.items.length === 0) {
            page.value = Math.max(1, Math.ceil(result.pagination.total / result.pagination.perPage))
            await load()
            return
        }
        data.value = result
        notifications.unreadCount = result.unreadCount
        error.value = ''
    } catch (e) {
        if (version === requestVersion) error.value = e instanceof Error ? e.message : 'Не удалось загрузить уведомления'
    }
}
async function markRead(id?: string) {
    busy.value = true
    try {
        await auth.withToken((token) => (id ? notificationService.read(token, id) : notificationService.readAll(token)))
        await load()
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Не удалось отметить уведомление'
    } finally {
        busy.value = false
    }
}
async function demo() {
    busy.value = true
    try {
        const result = await auth.withToken(notificationService.demo)
        message.value = result.message
        await load()
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Не удалось отправить уведомление'
    } finally {
        busy.value = false
    }
}
function changePage(value: number) {
    page.value = value
    void load()
}
function filter() {
    page.value = 1
    void load()
}
watch(
    () => notifications.revision,
    () => {
        void load()
    },
)
onMounted(() => {
    void load()
    timer = setInterval(() => {
        if (!notifications.connected && !document.hidden && !busy.value) void load()
    }, 15_000)
})
onBeforeUnmount(() => {
    clearInterval(timer)
    requestVersion++
})
</script>
<template>
    <main>
        <PageHeading title="Уведомления">
            <AppButton :disabled="busy" @click="demo" variant="secondary"> Тестовое уведомление </AppButton>
        </PageHeading>
        <AppNotice :message="error" />
        <AppNotice :message="message" type="success" />
        <section class="min-w-0">
            <div
                class="flex flex-wrap items-center justify-between gap-4 pb-[18px] [&_h2]:m-0 [&_p]:mt-1 [&_p]:mb-0 [&_p]:text-xs [&_p]:text-muted">
                <label class="flex items-center gap-2 text-[13px]"
                    ><input v-model="unreadOnly" type="checkbox" @change="filter" /> Только непрочитанные</label
                >
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton :disabled="busy" @click="load" variant="secondary">Обновить</AppButton>
                    <AppButton :disabled="busy || !notifications.unreadCount" @click="markRead()" variant="secondary">
                        Прочитать все
                    </AppButton>
                </div>
            </div>
            <ul v-if="data?.items.length" class="m-0 list-none p-0">
                <NotificationItem v-for="item in data.items" :key="item.id" :item="item" :busy="busy" @read="markRead" />
            </ul>
            <div v-else class="py-7 text-left text-muted">
                {{ data ? 'Уведомлений пока нет.' : 'Загрузка уведомлений…' }}
            </div>
            <AppPagination
                v-if="data && pages > 1"
                :page="page"
                :total="data.pagination.total"
                :per-page="data.pagination.perPage"
                :disabled="busy"
                @change="changePage" />
        </section>
    </main>
</template>
