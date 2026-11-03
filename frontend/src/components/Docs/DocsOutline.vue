<script setup lang="ts">
import { RouterLink } from 'vue-router'
import type { DocumentationHeading } from '@/services/Docs/documentation'

withDefaults(
    defineProps<{
        items?: DocumentationHeading[]
        activeId?: string
    }>(),
    { items: () => [], activeId: '' },
)
</script>

<template>
    <aside>
        <nav class="border-l border-miku/50 pl-5">
            <h2 class="mb-3 text-xs leading-6 font-semibold text-docs-heading">На этой странице</h2>
            <ul v-if="items.length" class="space-y-1">
                <li v-for="item in items" :key="item.id" :class="{ 'pl-3': item.level === 3 }">
                    <RouterLink
                        :to="{ hash: `#${item.id}` }"
                        :class="activeId === item.id ? 'font-semibold text-docs-accent' : 'text-docs-muted'"
                        class="block py-1 text-xs leading-5 hover:text-docs-accent hover:no-underline">
                        {{ item.title }}
                    </RouterLink>
                </li>
            </ul>
        </nav>
    </aside>
</template>
