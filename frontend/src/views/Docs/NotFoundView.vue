<script setup lang="ts">
import DocsNotFound from '@/components/Docs/DocsNotFound.vue'
import { useDocsTheme } from '@/composables/useDocsTheme'

useDocsTheme()
</script>

<template>
    <main class="docs-page min-h-dvh bg-docs-background px-6 py-20 text-docs-ink antialiased md:py-32">
        <div class="mx-auto max-w-[688px]">
            <DocsNotFound />
        </div>
    </main>
</template>
