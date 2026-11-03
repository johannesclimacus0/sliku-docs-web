<script setup lang="ts">
import { RouterLink } from 'vue-router'

withDefaults(defineProps<{ title?: string; description?: string; version?: string }>(), {
    title: 'Страница не найдена',
    description: 'Проверьте адрес или откройте руководство с начала.',
})
</script>

<template>
    <section class="py-8">
        <p class="mb-5 font-mono text-sm text-docs-accent">404</p>
        <h1 class="mb-5 text-3xl leading-tight font-semibold tracking-tight text-docs-heading">{{ title }}</h1>
        <p class="mb-8 max-w-lg text-base leading-7 text-docs-muted">{{ description }}</p>
        <RouterLink
            :to="version ? { name: 'documentation', params: { version, page: 'installation' } } : { name: 'home' }"
            class="inline-flex rounded-md border border-docs-line bg-docs-soft px-4 py-2.5 text-sm font-medium text-docs-accent hover:border-miku hover:no-underline">
            Открыть документацию
        </RouterLink>
    </section>
</template>
