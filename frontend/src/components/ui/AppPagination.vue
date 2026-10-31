<script setup lang="ts">
import { computed } from 'vue'
import AppButton from './AppButton.vue'
const props = defineProps<{
    page: number
    total: number
    perPage: number
    disabled?: boolean
    compact?: boolean
}>()
const emit = defineEmits<{ change: [page: number] }>()
const pages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)))
</script>
<template>
    <div class="flex items-center justify-between gap-3 py-5 text-xs text-muted">
        <span :class="{ 'order-1': !$slots.default }"
            ><slot>{{ page }} / {{ pages }}</slot></span
        >
        <div :class="$slots.default ? 'flex gap-2' : 'contents'">
            <AppButton variant="secondary" :compact="compact" :disabled="page <= 1 || disabled" @click="emit('change', page - 1)"
                >Назад</AppButton
            >
            <AppButton
                variant="secondary"
                :compact="compact"
                :disabled="page >= pages || disabled"
                :class="{ 'order-2': !$slots.default }"
                @click="emit('change', page + 1)"
                >Далее</AppButton
            >
        </div>
    </div>
</template>
