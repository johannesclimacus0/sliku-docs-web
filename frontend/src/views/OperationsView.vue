<script setup lang="ts">
import PageHeading from '@/components/ui/PageHeading.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { accountService, dateTime, type Operations } from '@/services/account'
const auth = useAuthStore()
const data = ref<Operations | null>(null)
const busy = ref('')
const error = ref('')
const result = ref('')
async function load() {
    try {
        data.value = await auth.withToken(accountService.operations)
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
    }
}
async function run(action: 'job' | 'webhook' | 'schedule' | 'http', failure = false) {
    if (busy.value) return
    busy.value = failure ? 'failure' : action
    error.value = ''
    result.value = ''
    try {
        const response = await auth.withToken((t) => accountService.demo(t, action, action === 'job' ? { failure } : {}))
        result.value = response.message ?? JSON.stringify(response, null, 2)
        await load()
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка запроса'
    } finally {
        busy.value = ''
    }
}
onMounted(load)
</script>
<template>
    <main>
        <PageHeading title="Инструменты">
            <AppButton @click="load">Обновить</AppButton>
        </PageHeading>
        <AppNotice :message="error" />
        <pre
            v-if="result"
            class="max-h-[250px] overflow-auto border border-line p-4 text-xs whitespace-pre-wrap [overflow-wrap:anywhere]"
            >{{ result }}</pre>
        <dl
            v-if="data"
            class="mt-0 mb-8 flex flex-wrap gap-x-10 gap-y-[22px] [&_dt]:text-xs [&_dt]:text-muted [&_dd]:mt-1 [&_dd]:text-xl [&_dd]:tabular-nums">
            <div>
                <dt>Пользователи</dt>
                <dd>{{ data.counts.users }}</dd>
            </div>
            <div>
                <dt>В очереди</dt>
                <dd>{{ data.counts.queued }}</dd>
            </div>
            <div>
                <dt>Ошибки очереди</dt>
                <dd>{{ data.counts.failed }}</dd>
            </div>
            <div>
                <dt>Вебхуки</dt>
                <dd>{{ data.counts.webhooks }}</dd>
            </div>
        </dl>
        <section class="my-9 [&>h2]:mb-4">
            <h2>Действия</h2>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-line py-[13px]">
                <span>Очередь</span>
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton :disabled="!!busy" @click="run('job')">Создать задание</AppButton
                    ><AppButton :disabled="!!busy" @click="run('job', true)"> Проверить повторы </AppButton>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-line py-[13px]">
                <span>Планировщик</span><AppButton :disabled="!!busy" @click="run('schedule')"> Запустить задачи </AppButton>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-4 border-t border-line py-[13px]">
                <span>Внешние запросы</span>
                <div class="flex flex-wrap items-center gap-2">
                    <AppButton :disabled="!!busy" @click="run('webhook')">Отправить вебхук</AppButton
                    ><AppButton :disabled="!!busy" @click="run('http')">Проверить HTTP</AppButton>
                </div>
            </div>
        </section>
        <section class="my-9 [&>h2]:mb-4">
            <h2>Сервисы</h2>
            <div
                v-for="(state, name) in data?.health.checks"
                :key="name"
                class="flex items-center justify-between gap-[14px] border-b border-line py-3 text-[13px]">
                <span>{{ name }}</span
                ><span>{{ state }}</span>
            </div>
            <div class="mt-5 flex flex-wrap gap-5 text-[13px] underline">
                <a href="http://localhost:8025" target="_blank" rel="noreferrer">Mailpit</a
                ><a href="http://localhost:8080/clockwork/" target="_blank" rel="noreferrer">Clockwork</a
                ><a href="http://localhost:8081" target="_blank" rel="noreferrer">OpenAPI</a>
            </div>
        </section>
        <section class="my-9 [&>h2]:mb-4">
            <h2>Расписание</h2>
            <div
                v-for="task in data?.schedule"
                :key="task.command"
                class="flex items-center justify-between gap-[14px] border-b border-line py-3 text-[13px] [&_small]:mt-1 [&_small]:block [&_small]:text-muted">
                <div>
                    <code>{{ task.command }}</code
                    ><small>Каждые {{ task.every }} сек.</small>
                </div>
                <span>{{ task.lastRun ? dateTime(task.lastRun) : 'Ещё не запускалась' }}</span>
            </div>
        </section>
        <section class="my-9 [&>h2]:mb-4">
            <h2>Журнал вебхуков</h2>
            <div class="overflow-auto">
                <table
                    class="w-full border-collapse text-left text-[13px] [&_th]:border-b [&_th]:border-line [&_th]:px-3 [&_th]:py-[15px] [&_th]:font-normal [&_th]:whitespace-nowrap [&_th]:text-muted [&_td]:border-b [&_td]:border-line [&_td]:px-3 [&_td]:py-[15px] [&_td]:whitespace-nowrap [&_th:first-child]:pl-0 [&_td:first-child]:pl-0 [&_td>small]:mt-[3px] [&_td>small]:block [&_td>small]:text-muted">
                    <thead>
                        <tr>
                            <th>Событие</th>
                            <th>Статус</th>
                            <th>Попытки</th>
                            <th>HTTP</th>
                            <th>Время</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in data?.deliveries" :key="item.id">
                            <td>
                                <strong>{{ item.event_name }}</strong
                                ><small class="font-mono text-muted" :title="item.id">{{ item.id.slice(0, 16) }}…</small>
                            </td>
                            <td>{{ item.status }}</td>
                            <td>{{ item.attempts }}</td>
                            <td>{{ item.last_http_status ?? '—' }}</td>
                            <td>{{ dateTime(item.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
                <p v-if="!data?.deliveries.length" class="py-7 text-left text-muted">Доставок пока нет.</p>
            </div>
        </section>
    </main>
</template>
