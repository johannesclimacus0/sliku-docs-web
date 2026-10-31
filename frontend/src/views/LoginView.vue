<script setup lang="ts">
import FormField from '@/components/ui/FormField.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { useAuthStore } from '@/stores/auth'
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const email = ref('')
const password = ref('')
const remember = ref(false)
const busy = ref(false)
const error = ref('')
async function submit() {
    if (busy.value) return
    busy.value = true
    error.value = ''
    try {
        await auth.login(email.value, password.value, remember.value)
        password.value = ''
        const redirect = route.query.redirect
        await router.push(typeof redirect === 'string' && redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/')
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка входа'
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <AuthLayout title="Вход"
        ><AppNotice :message="error" />
        <form @submit.prevent="submit">
            <FormField
                label="Email"
                id="login-email"
                v-model="email"
                type="email"
                autocomplete="email"
                placeholder="you@example.com"
                required />
            <FormField label="Пароль" id="login-password" v-model="password" type="password" autocomplete="current-password" required
                ><template #labelAction><RouterLink to="/forgot-password">Забыли пароль?</RouterLink></template></FormField
            >
            <label class="my-5 flex items-center gap-2 text-[13px]"><input v-model="remember" type="checkbox" /> Запомнить меня</label
            ><AppButton :disabled="busy" variant="primary" full-width type="submit">
                {{ busy ? 'Входим...' : 'Войти' }}
            </AppButton>
        </form>
        <template #footer>Нет аккаунта? <RouterLink to="/register">Зарегистрироваться</RouterLink></template></AuthLayout
    >
</template>
