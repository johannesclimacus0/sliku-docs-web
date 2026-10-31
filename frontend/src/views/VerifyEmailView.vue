<script setup lang="ts">
import AppNotice from '@/components/ui/AppNotice.vue'
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authService } from '@/services/auth'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const params = new URLSearchParams(route.hash.slice(1))
const userId = Number(params.get('userId'))
const token = ref(params.get('token') ?? '')
const valid = computed(() => Number.isSafeInteger(userId) && userId > 0 && /^[a-f0-9]{64}$/.test(token.value))
const busy = ref(false)
const success = ref(false)
const error = ref('')

onMounted(() => {
    if (route.hash) void router.replace({ name: 'verify-email', hash: '' })
    void confirm()
})

async function confirm(): Promise<void> {
    if (!valid.value || busy.value || success.value) return
    busy.value = true
    error.value = ''
    try {
        await authService.verifyEmail(userId, token.value)
        success.value = true
        token.value = ''
        if (auth.user?.id === userId) {
            try {
                await auth.loadMe()
            } catch {}
        }
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка подтверждения'
    } finally {
        busy.value = false
    }
}
</script>

<template>
    <main class="mx-auto my-20 w-[calc(100%-40px)] max-w-[560px]">
        <h1>Подтверждение email</h1>
        <template v-if="success">
            <AppNotice message="Email подтверждён." type="success" />
            <RouterLink to="/profile">Открыть профиль</RouterLink>
            <p v-if="!auth.user"><RouterLink to="/login">Войти в аккаунт</RouterLink></p>
        </template>
        <template v-else-if="valid">
            <p v-if="busy">Подтверждаем email...</p>
            <AppNotice :message="error" />
        </template>
        <template v-else>
            <p>Ссылка неполная или уже использована в этой вкладке. Запросите новое письмо в своём аккаунте.</p>
            <RouterLink to="/login">Открыть аккаунт</RouterLink>
        </template>
    </main>
</template>
