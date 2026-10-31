<script setup lang="ts">
import AppPagination from '@/components/ui/AppPagination.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import PageHeading from '@/components/ui/PageHeading.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { User } from '@/services/auth'
import { accountService, type Directory } from '@/services/account'
import ProfileForm from '@/components/ProfileForm.vue'
const auth = useAuthStore()
const router = useRouter()
const data = ref<Directory | null>(null)
const page = ref(1)
const search = ref('')
const selected = ref<User | null>(null)
const role = ref<User['role']>('member')
const busy = ref(false)
const error = ref('')
async function load(reset = false) {
    if (busy.value) return
    if (reset) page.value = 1
    busy.value = true
    error.value = ''
    try {
        data.value = await auth.withToken((t) => accountService.users(t, page.value, search.value))
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
    } finally {
        busy.value = false
    }
}
async function open(id: number) {
    error.value = ''
    try {
        selected.value = await auth.withToken((t) => accountService.user(t, id))
        role.value = selected.value.role
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
    }
}
async function changeRole() {
    if (!selected.value || busy.value) return
    busy.value = true
    error.value = ''
    try {
        const u = await auth.withToken((t) => accountService.role(t, selected.value!.id, role.value))
        selected.value = u
        if (auth.user?.id === u.id) {
            auth.user = u
            if (u.role !== 'admin') await router.replace('/')
        }
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка изменения роли'
    } finally {
        busy.value = false
        if (auth.user?.role === 'admin') await load()
    }
}
function saved(user: User) {
    selected.value = user
    void load()
}
function changePage(value: number) {
    if (busy.value) return
    page.value = value
    void load()
}
onMounted(() => load())
</script>
<template>
    <main>
        <PageHeading title="Пользователи">
            <span class="text-xs text-[#666]">{{ data?.cache.hit ? 'Из кэша' : 'Обновлено' }} · 60 сек.</span>
        </PageHeading>
        <AppNotice :message="error" />
        <section class="min-w-0">
            <form class="flex flex-wrap items-center gap-3 pb-5" @submit.prevent="load(true)">
                <div
                    class="max-w-[360px] min-w-40 flex-1 [&_input]:w-full [&_input]:rounded [&_input]:border [&_input]:border-control [&_input]:px-[11px] [&_input]:py-2">
                    <input v-model="search" placeholder="Поиск по имени или email" maxlength="80" />
                </div>
                <AppButton :disabled="busy" variant="secondary" type="submit">Найти</AppButton
                ><AppButton type="button" :disabled="busy" @click="load()" variant="icon"> Обновить </AppButton>
            </form>
            <div class="overflow-auto">
                <table
                    class="w-full border-collapse text-left text-[13px] [&_th]:border-b [&_th]:border-line [&_th]:px-3 [&_th]:py-[15px] [&_th]:font-normal [&_th]:whitespace-nowrap [&_th]:text-muted [&_td]:border-b [&_td]:border-line [&_td]:px-3 [&_td]:py-[15px] [&_td]:whitespace-nowrap [&_th:first-child]:pl-0 [&_td:first-child]:pl-0 [&_td>small]:mt-[3px] [&_td>small]:block [&_td>small]:text-muted">
                    <thead>
                        <tr>
                            <th>Участник</th>
                            <th>Роль</th>
                            <th>Email</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in data?.items" :key="user.id">
                            <td>
                                <div
                                    class="flex items-center gap-[10px] [&_strong]:block [&_strong]:font-medium [&_small]:mt-[3px] [&_small]:block [&_small]:text-muted">
                                    <UserAvatar :name="user.name" :src="user.avatarUrl" />
                                    <div>
                                        <strong>{{ user.name }}</strong
                                        ><small>{{ user.email }}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-xs text-[#666]">{{ user.role === 'admin' ? 'Администратор' : 'Участник' }}</span>
                            </td>
                            <td>
                                <span class="text-[#555]">{{ user.emailVerifiedAt ? 'Подтверждён' : 'Ожидает подтверждения' }}</span>
                            </td>
                            <td>
                                <AppButton @click="open(user.id)" variant="text">Открыть →</AppButton>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div v-if="data && !data.items.length" class="py-7 text-left text-muted">Пользователи не найдены.</div>
                <div v-if="!data" class="py-7 text-left text-muted">Загружаем каталог...</div>
            </div>
            <AppPagination
                v-if="data"
                :page="page"
                :total="data.pagination.total"
                :per-page="data.pagination.perPage"
                :disabled="busy"
                compact
                @change="changePage">
                Всего {{ data.pagination.total }} -|- страница {{ page }}
            </AppPagination>
        </section>
        <section v-if="selected" class="min-w-0 mt-9 border-t border-line pt-[26px]">
            <div
                class="flex flex-wrap items-center justify-between gap-4 pb-[18px] [&_h2]:m-0 [&_p]:mt-1 [&_p]:mb-0 [&_p]:text-xs [&_p]:text-muted">
                <div>
                    <h2>{{ selected.name }}</h2>
                    <p>{{ selected.email }} -|- ID {{ selected.id }}</p>
                </div>
                <AppButton @click="selected = null" variant="text">Закрыть</AppButton>
            </div>
            <div class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] gap-10 [&_select]:mt-2 max-[760px]:grid-cols-1 max-[760px]:gap-6">
                <ProfileForm :user="selected" @saved="saved" />
                <div>
                    <h3>Права доступа</h3>
                    <p class="text-muted">Роль применяется сервером при следующем запросе. Последнего администратора понизить нельзя.</p>
                    <label
                        class="mb-5 block [&_label]:mb-[7px] [&_label]:block [&_label]:text-[13px] [&_select]:block [&_select]:w-full [&_select]:rounded [&_select]:border [&_select]:border-control [&_select]:bg-white [&_select]:px-[11px] [&_select]:py-[9px]"
                        >Роль<select v-model="role">
                            <option value="member">Участник</option>
                            <option value="admin">Администратор</option>
                        </select></label
                    ><AppButton :disabled="busy || role === selected.role" @click="changeRole" variant="secondary">
                        Изменить роль
                    </AppButton>
                </div>
            </div>
        </section>
    </main>
</template>
