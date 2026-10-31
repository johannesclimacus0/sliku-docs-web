<script setup lang="ts">
import FormField from '@/components/ui/FormField.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { ref } from 'vue'
import AuthLayout from '@/layouts/AuthLayout.vue'
import { authService } from '@/services/auth'

const email = ref('')
const busy = ref(false)
const message = ref('')
const error = ref('')

async function submit(): Promise<void> {
    if (busy.value) return
    busy.value = true
    message.value = ''
    error.value = ''
    try {
        const result = await authService.forgotPassword(email.value)
        message.value = result.message
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Не удалось запросить письмо'
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <AuthLayout title="Восстановление пароля">
        <AppNotice :message="message" type="success" />
        <AppNotice :message="error" />
        <form @submit.prevent="submit">
            <FormField label="Email" id="recovery-email" v-model="email" type="email" autocomplete="email" required />
            <AppButton :disabled="busy" variant="primary" type="submit">
                {{ busy ? 'Отправляем...' : 'Отправить ссылку' }}
            </AppButton>
        </form>
        <template #footer><RouterLink to="/login">Вернуться ко входу</RouterLink></template>
    </AuthLayout>
</template>
