<script setup lang="ts">
withDefaults(defineProps<{ name: string; src?: string | null; size?: 'small' | 'profile' }>(), { size: 'small' })
</script>
<template>
    <img
        v-if="src"
        :src="src"
        :class="size === 'profile' ? 'size-20 rounded object-cover max-[680px]:size-16' : 'size-8 shrink-0 rounded object-cover'" />
    <span
        v-else
        :class="
            size === 'profile'
                ? 'grid size-20 place-items-center rounded bg-[#ededed] text-[21px] text-[#777] max-[680px]:size-16'
                : 'grid size-8 shrink-0 place-items-center rounded bg-[#e8e8e8] text-[11px] text-[#555]'
        "
        >{{ name.slice(0, 2).toUpperCase() }}</span
    >
</template>
