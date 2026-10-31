<script setup lang="ts">
defineProps<{ title: string }>()
</script>
<template>
    <header
        class="mb-[30px] flex flex-wrap items-center justify-between gap-4 border-b border-line pb-[22px] [&_h1]:m-0 [&_p]:mt-[6px] [&_p]:mb-0 max-[680px]:gap-3">
        <h1>{{ title }}</h1>
        <slot />
    </header>
</template>
