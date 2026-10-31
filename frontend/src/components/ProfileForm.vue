<script setup lang="ts">
import FormField from '@/components/ui/FormField.vue'
import AppButton from '@/components/ui/AppButton.vue'
import AppNotice from '@/components/ui/AppNotice.vue'
import { ref, watch } from 'vue'
import type { User } from '@/services/auth'
import { accountService } from '@/services/account'
import { useAuthStore } from '@/stores/auth'
const props = withDefaults(defineProps<{ user: User; disabled?: boolean }>(), { disabled: false })
const emit = defineEmits<{ saved: [user: User] }>()
const auth = useAuthStore()
const name = ref(props.user.name)
const bio = ref(props.user.bio)
const busy = ref(false)
const message = ref('')
const error = ref('')
watch(
    () => props.user,
    (user, previous) => {
        name.value = user.name
        bio.value = user.bio
        if (user.id !== previous?.id) {
            message.value = ''
            error.value = ''
        }
    },
)
async function save() {
    if (busy.value || props.disabled) return
    busy.value = true
    error.value = ''
    message.value = ''
    try {
        const user = await auth.withToken((token) => accountService.profile(token, props.user.id, name.value, bio.value))
        if (auth.user?.id === user.id) auth.user = user
        emit('saved', user)
        message.value = 'Профиль сохранён. Фоновые реакции поставлены в очередь'
    } catch (e) {
        error.value = e instanceof Error ? e.message : 'Ошибка сохранения'
    } finally {
        busy.value = false
    }
}
</script>
<template>
    <form @submit.prevent="save">
        <AppNotice :message="error" />
        <AppNotice :message="message" type="success" />
        <FormField label="Имя" :id="`name-${user.id}`" v-model="name" maxlength="80" minlength="2" required :disabled="busy || disabled" />
        <FormField
            label="О себе"
            :id="`bio-${user.id}`"
            v-model="bio"
            maxlength="500"
            rows="4"
            :disabled="busy || disabled"
            placeholder="Несколько слов о себе"
            multiline
            ><template #hint>{{ bio.length }} / 500</template></FormField
        >
        <div class="mt-[22px]">
            <AppButton :disabled="busy || disabled" variant="primary" type="submit">
                {{ busy ? 'Сохраняем...' : 'Сохранить изменения' }}
            </AppButton>
        </div>
    </form>
</template>
