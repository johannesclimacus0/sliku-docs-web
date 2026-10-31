<script setup lang="ts">
import FormField from '@/components/ui/FormField.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { authService } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const params = new URLSearchParams(route.hash.slice(1))
const userId = Number(params.get('userId'))
const token = ref(params.get('token') ?? '')
const valid = computed(() => Number.isSafeInteger(userId) && userId > 0 && /^[a-f0-9]{64}$/.test(token.value))
const password = ref('')
const confirmation = ref('')
const busy = ref(false)
const success = ref(false)
const error = ref('')

onMounted(() => {
    if (route.hash) void router.replace({ name: 'reset-password', hash: '' })
})

async function submit(): Promise<void> {
    if (busy.value || !valid.value || success.value) return
    error.value = ''
    if (password.value !== confirmation.value) {
        error.value = 'Пароли не совпадают'
        return
    }
    busy.value = true
    try {
        await authService.resetPassword(userId, token.value, password.value)
        if (auth.user?.id === userId) {
            auth.accessToken = null
            auth.user = null
        }
        password.value = ''
        confirmation.value = ''
        token.value = ''
        success.value = true
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Не удалось изменить пароль'
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <AuthLayout title="Новый пароль">
        <AppNotice v-if="success" message="Пароль изменён. Войдите с новым паролем." type="success" />
        <form v-else-if="valid" @submit.prevent="submit">
            <AppNotice :message="error" />
            <FormField
                label="Новый пароль"
                id="new-password"
                v-model="password"
                type="password"
                autocomplete="new-password"
                minlength="12"
                required />
            <FormField
                label="Повторите пароль"
                id="repeat-password"
                v-model="confirmation"
                type="password"
                autocomplete="new-password"
                required />
            <AppButton :disabled="busy" variant="primary" type="submit">
                {{ busy ? 'Сохраняем...' : 'Сохранить пароль' }}
            </AppButton>
        </form>
        <p v-else>Ссылка неполная. Откройте её из письма заново или запросите новую.</p>
        <template #footer>
            <RouterLink to="/login">Войти</RouterLink>
            <RouterLink v-if="!success" to="/forgot-password">Запросить новую ссылку</RouterLink>
        </template>
    </AuthLayout>
</template>
