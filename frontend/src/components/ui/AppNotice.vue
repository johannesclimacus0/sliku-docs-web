<script setup lang="ts">
withDefaults(defineProps<{ message: string; type?: 'error' | 'success' }>(), { type: 'error' })
</script>
<template>
    <p
        v-if="message"
        class="mb-6 rounded border bg-[#fafafa] px-[14px] py-3"
        :class="type === 'error' ? 'border-[#ead1d1] text-[#9c2828]' : 'border-line text-[#454545]'">
        {{ message }}
    </p>
</template>
