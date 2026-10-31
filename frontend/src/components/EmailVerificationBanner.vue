<script setup lang="ts">
import AppButton from '@/components/ui/AppButton.vue'
import { ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { authService } from '@/services/auth'

const auth = useAuthStore()
const busy = ref(false)
const message = ref('')
const error = ref('')

async function resend(): Promise<void> {
    if (busy.value) return
    busy.value = true
    message.value = ''
    error.value = ''
    try {
        const result = await auth.withToken((token) => authService.resendVerification(token))
        message.value = result.message
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка отправки'
    } finally {
        busy.value = false
    }
}

async function check(): Promise<void> {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
        await auth.loadMe()
        if (auth.user && !auth.user.emailVerifiedAt) message.value = 'Email ещё не подтверждён. Откройте ссылку из письма.'
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка проверки'
    } finally {
        busy.value = false
    }
}

watch(
    () => auth.user?.id,
    () => {
        message.value = ''
        error.value = ''
    },
)
</script>

<template>
    <aside v-if="auth.user && !auth.user.emailVerifiedAt" class="mb-6 rounded border border-line bg-[#fafafa] px-[14px] py-3 [&_p]:mb-2">
        <p>
            <strong>Подтвердите {{ auth.user.email }}</strong>
        </p>
        <p v-if="auth.user.emailVerificationRequired">
            До подтверждения доступен просмотр аккаунта. Чтобы изменять данные и запускать действия, откройте ссылку из письма
        </p>
        <p v-else>Откройте ссылку из письма, чтобы подтвердить свой адрес</p>
        <div class="flex flex-wrap items-center gap-2">
            <AppButton :disabled="busy" @click="resend" variant="secondary" compact> Отправить письмо ещё раз </AppButton>
            <AppButton :disabled="busy" @click="check" variant="secondary" compact> Я подтвердил — обновить </AppButton>
        </div>
        <p v-if="message">{{ message }}</p>
        <p v-if="error">{{ error }}</p>
    </aside>
</template>
