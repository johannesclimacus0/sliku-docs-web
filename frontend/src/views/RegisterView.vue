<script setup lang="ts">
import FormField from '@/components/ui/FormField.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()
const email = ref('')
const password = ref('')
const busy = ref(false)
const message = ref('')

async function submitRegister(): Promise<void> {
    if (busy.value) return

    busy.value = true
    message.value = ''

    try {
        await auth.register(email.value, password.value)
        password.value = ''
        await router.push('/profile')
    } catch (error) {
        message.value = error instanceof Error ? error.message : 'Ошибка регистрации'
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <AuthLayout title="Создать аккаунт">
        <AppNotice :message="message" />
        <p v-if="!auth.ready" class="text-muted">Проверяем вход...</p>

        <p v-else-if="auth.user" class="text-muted">
            Вы уже вошли как {{ auth.user.email }}.
            <RouterLink to="/login">Вернуться ко входу</RouterLink>
        </p>

        <form v-else @submit.prevent="submitRegister">
            <FormField
                label="Email"
                id="register-email"
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="name@example.com"
                required />
            <FormField
                label="Пароль"
                id="register-password"
                v-model="password"
                type="password"
                autocomplete="new-password"
                placeholder="Введите пароль"
                required />
            <AppButton type="submit" :disabled="busy" variant="primary">
                {{ busy ? 'Создаём...' : 'Создать аккаунт' }}
            </AppButton>
        </form>

        <template #footer>
            <span v-if="!auth.user"> Уже есть аккаунт? <RouterLink to="/login">Войти</RouterLink> </span>
        </template>
    </AuthLayout>
</template>
