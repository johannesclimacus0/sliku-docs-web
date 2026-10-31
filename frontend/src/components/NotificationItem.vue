<script setup lang="ts">
import type { NotificationList } from '@/services/notifications'
import { dateTime } from '@/services/account'
import AppButton from '@/components/ui/AppButton.vue'
defineProps<{ item: NotificationList['items'][number]; busy?: boolean }>()
const emit = defineEmits<{ read: [id: string] }>()
</script>
<template>
    <li class="flex items-baseline justify-between gap-6 border-t border-line py-[22px] max-[680px]:flex-wrap max-[680px]:gap-3">
        <div class="min-w-0 [overflow-wrap:anywhere] [&_p]:my-[6px] [&_p]:whitespace-pre-line">
            <strong :class="item.readAt === null ? 'font-semibold text-[#222]' : 'font-normal text-[#777]'">{{ item.title }}</strong>
            <p>{{ item.body }}</p>
            <small class="text-muted">{{ dateTime(item.createdAt) }}</small>
        </div>
        <AppButton v-if="item.readAt === null" :disabled="busy" @click="emit('read', item.id)" variant="secondary"> Прочитано </AppButton>
        <span v-else class="text-xs text-[#666]">Прочитано</span>
    </li>
</template>
