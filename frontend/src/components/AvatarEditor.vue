<script setup lang="ts">
import UserAvatar from '@/components/ui/UserAvatar.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { ref, watch } from 'vue'
import { authService } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const file = ref<File | null>(null)
const input = ref<HTMLInputElement | null>(null)
const busy = ref(false)
const error = ref('')
const message = ref('')

function select(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null
    error.value = ''
    message.value = ''
}

async function upload(): Promise<void> {
    const selected = file.value
    const userId = auth.user?.id
    if (!selected || !userId || busy.value || auth.user?.emailVerificationRequired) return
    busy.value = true
    error.value = ''
    message.value = ''
    try {
        const result = await auth.withToken((token) => authService.uploadAvatar(selected, token))
        if (auth.user?.id === userId) {
            auth.user.avatarUrl = result.avatarUrl
            message.value = 'Аватар обновлён.'
            file.value = null
            if (input.value) input.value.value = ''
        }
    } catch (e) {
        if (auth.user?.id === userId) error.value = e instanceof Error ? e.message : 'Ошибка загрузки'
    } finally {
        busy.value = false
    }
}

async function remove(): Promise<void> {
    const userId = auth.user?.id
    if (!userId || busy.value || auth.user?.emailVerificationRequired) return
    busy.value = true
    error.value = ''
    message.value = ''
    try {
        await auth.withToken((token) => authService.deleteAvatar(token))
        if (auth.user?.id === userId) {
            auth.user.avatarUrl = null
            message.value = 'Аватар удалён.'
        }
    } catch (e) {
        if (auth.user?.id === userId) error.value = e instanceof Error ? e.message : 'Ошибка удаления'
    } finally {
        busy.value = false
    }
}

watch(
    () => auth.user?.id,
    () => {
        file.value = null
        error.value = ''
        message.value = ''
        if (input.value) input.value.value = ''
    },
)
</script>

<template>
    <section
        v-if="auth.user"
        class="grid grid-cols-[80px_minmax(0,1fr)] items-start gap-5 [&>form]:col-start-2 [&>form]:min-w-0 [&>p]:col-start-2 [&>p]:min-w-0 max-[680px]:grid-cols-[64px_minmax(0,1fr)] max-[680px]:gap-[14px]">
        <div class="col-start-1 row-span-4 row-start-1">
            <UserAvatar :name="auth.user.name || auth.user.email" :src="auth.user.avatarUrl" size="profile" />
        </div>
        <AppNotice :message="error" />
        <AppNotice :message="message" type="success" />
        <form v-if="!auth.user.emailVerificationRequired" @submit.prevent="upload">
            <div class="[&_label]:mb-[7px] [&_label]:block [&_label]:text-xs [&_label]:text-muted">
                <label for="avatar-file">JPEG, PNG или WebP, до 2 МБ</label>
                <input
                    id="avatar-file"
                    ref="input"
                    type="file"
                    class="block w-full border-0 p-0 text-xs file:mr-2 file:rounded file:border file:border-control file:bg-[#f5f5f5] file:px-2 file:py-1"
                    accept="image/jpeg,image/png,image/webp"
                    :disabled="busy"
                    @change="select" />
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-2">
                <AppButton :disabled="busy || !file" variant="primary" type="submit">
                    {{ busy ? 'Подождите...' : 'Загрузить' }}
                </AppButton>
                <AppButton v-if="auth.user.avatarUrl" type="button" :disabled="busy" @click="remove" variant="secondary">
                    Удалить
                </AppButton>
            </div>
        </form>
        <p v-else class="text-muted">Подтвердите email, чтобы изменить аватар</p>
    </section>
</template>
