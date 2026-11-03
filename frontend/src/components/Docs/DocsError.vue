<script setup lang="ts">
defineProps<{ title: string; message: string }>()
</script>

<template>
    <section class="flex min-h-[45vh] flex-col items-center justify-center px-6 py-12 text-center">
        <h1 class="m-0 max-w-md text-2xl leading-8 font-medium tracking-tight text-docs-heading">{{ title }}</h1>
        <p class="mt-4 mb-0 max-w-80 text-sm leading-6 break-words text-docs-muted">{{ message }}</p>
    </section>
</template>
