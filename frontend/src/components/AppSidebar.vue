<script setup lang="ts">
import type { User } from '@/services/auth'
import AppButton from '@/components/ui/AppButton.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
defineProps<{
    user: User | null
    nav: { to: string; label: string; exact?: boolean }[]
    currentPath: string
    unreadCount: number
    leaving: boolean
}>()
const emit = defineEmits<{ logout: [] }>()
</script>
<template>
    <aside
        class="sticky top-0 flex h-dvh flex-col self-start overflow-y-auto border-r border-line bg-[#f6f6f6] px-[14px] pt-7 pb-5 max-[680px]:static max-[680px]:h-auto max-[680px]:border-r-0 max-[680px]:border-b max-[680px]:p-3">
        <nav class="grid gap-[5px] max-[680px]:flex max-[680px]:overflow-auto">
            <RouterLink
                v-for="item in nav"
                :key="item.to"
                :to="item.to"
                class="flex items-center gap-[10px] rounded px-[10px] py-2 hover:no-underline max-[680px]:text-xs max-[680px]:whitespace-nowrap"
                :class="
                    (item.exact ? currentPath === item.to : currentPath.startsWith(item.to))
                        ? 'bg-[#e7e7e7] text-[#222] hover:bg-[#e7e7e7]'
                        : 'text-[#666] hover:bg-[#ededed]'
                "
                >{{ item.label
                }}<span v-if="item.to === '/notifications' && unreadCount" class="ml-auto text-xs text-[#666] tabular-nums">{{
                    unreadCount
                }}</span></RouterLink
            >
        </nav>
        <div class="mt-auto pt-6 max-[680px]:pt-3">
            <div v-if="user" class="flex items-center gap-2 border-t border-line pt-4 max-[680px]:border-0 max-[680px]:p-0">
                <UserAvatar :name="user.name" :src="user.avatarUrl" />
                <div class="min-w-0 flex-1">
                    <strong class="block truncate text-xs">{{ user.name }}</strong>
                    <small class="block text-[11px] text-muted">{{ user.role === 'admin' ? 'Администратор' : 'Участник' }}</small>
                </div>
                <AppButton title="Выйти" :disabled="leaving" @click="emit('logout')" variant="icon"> Выйти </AppButton>
            </div>
        </div>
    </aside>
</template>
