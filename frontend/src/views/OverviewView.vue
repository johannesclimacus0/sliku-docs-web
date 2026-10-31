<script setup lang="ts">
import PageHeading from '@/components/ui/PageHeading.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { accountService, activityLabel, dateTime, type Overview } from '@/services/account'
const auth = useAuthStore()
const data = ref<Overview | null>(null)
const error = ref('')
const busy = ref(false)
const message = ref('')
async function load() {
    try {
        data.value = await auth.withToken(accountService.overview)
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
    }
}
async function queueJob() {
    busy.value = true
    try {
        const r = await auth.withToken((t) => accountService.demo(t, 'job'))
        message.value = r.message ?? ''
        await load()
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка запроса'
    } finally {
        busy.value = false
    }
}
onMounted(load)
</script>
<template>
    <main>
        <PageHeading title="Обзор">
            <AppButton @click="load">Обновить</AppButton>
        </PageHeading>
        <AppNotice :message="error" />
        <AppNotice :message="message" type="success" />
        <div
            class="grid grid-cols-[250px_minmax(0,1fr)] items-start gap-14 max-[1050px]:grid-cols-[220px_minmax(0,1fr)] max-[1050px]:gap-[30px] max-[760px]:grid-cols-1 max-[760px]:gap-9">
            <section class="[&_h2]:mb-[3px] [&_h2]:text-lg [&>p]:[overflow-wrap:anywhere] [&_dl>div]:grid-cols-[1fr_auto]">
                <h2>{{ auth.user?.name }}</h2>
                <p class="text-muted">{{ auth.user?.email }}</p>
                <dl
                    v-if="data"
                    class="mt-0 mb-5 [&>div]:grid [&>div]:grid-cols-[150px_minmax(0,1fr)] [&>div]:gap-[14px] [&>div]:py-2 [&_dt]:text-[13px] [&_dt]:text-muted [&_dd]:m-0 [&_dd]:[overflow-wrap:anywhere] max-[680px]:[&>div]:grid-cols-1 max-[680px]:[&>div]:gap-[3px]">
                    <div>
                        <dt>Сессии</dt>
                        <dd>{{ data.stats.sessions }}</dd>
                    </div>
                    <div>
                        <dt>События</dt>
                        <dd>{{ data.stats.activities }}</dd>
                    </div>
                    <div>
                        <dt>Роль</dt>
                        <dd>{{ auth.user?.role === 'admin' ? 'Администратор' : 'Участник' }}</dd>
                    </div>
                </dl>
                <RouterLink to="/profile" class="text-[13px] underline underline-offset-[3px]">Изменить профиль</RouterLink>
                <div class="mt-7">
                    <AppButton :disabled="busy || auth.user?.emailVerificationRequired" @click="queueJob">
                        {{ busy ? 'Отправляем...' : 'Проверить очередь' }}
                    </AppButton>
                </div>
            </section>
            <section class="[&_h2]:mb-5">
                <h2>Последние события</h2>
                <ul
                    v-if="data?.activity.length"
                    class="m-0 list-none p-0 [&>li]:flex [&>li]:flex-col [&>li]:items-start [&>li]:gap-1 [&>li]:border-t [&>li]:border-line [&>li]:py-[14px] [&_strong]:font-normal">
                    <li v-for="item in data.activity" :key="item.id">
                        <strong>{{ activityLabel(item.action) }}</strong
                        ><small class="text-muted">{{ dateTime(item.created_at) }}</small>
                    </li>
                </ul>
                <p v-else class="py-7 text-left text-muted">Пока ничего нового</p>
            </section>
        </div>
    </main>
</template>
