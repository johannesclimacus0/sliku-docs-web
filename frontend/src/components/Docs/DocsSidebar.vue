<script setup lang="ts">
import { ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import type { DocumentationSection } from '@/services/Docs/documentation'

const props = defineProps<{
    version: string
    sections: DocumentationSection[]
}>()

const route = useRoute()
const openedSections = ref(new Set<string>())

watch(
    [() => props.version, () => props.sections, () => route.params.page],
    ([version, sections, page], previous) => {
        if (version !== previous?.[0]) openedSections.value = new Set()

        const current = sections.find((section) => section.items.some((item) => item.slug === page))
        if (current) openedSections.value.add(current.title)
    },
    { immediate: true },
)

function updateSection(title: string, event: Event) {
    const details = event.currentTarget as HTMLDetailsElement
    if (details.open) openedSections.value.add(title)
    else openedSections.value.delete(title)
}
</script>

<template>
    <aside>
        <nav>
            <details
                v-for="section in sections"
                :key="section.title"
                :open="openedSections.has(section.title)"
                @toggle="updateSection(section.title, $event)"
                class="group not-first:mt-6">
                <summary
                    class="flex cursor-pointer list-none items-center justify-between gap-3 py-1 text-[13px] leading-6 font-semibold text-docs-heading select-none hover:text-docs-accent [&::-webkit-details-marker]:hidden">
                    <span>{{ section.title }}</span>
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="h-3.5 w-3.5 shrink-0 text-docs-muted transition-transform group-open:rotate-90 group-open:text-docs-accent">
                        <path d="m9 18 6-6-6-6" />
                    </svg>
                </summary>
                <ul class="mt-1 list-none border-l border-docs-line pl-3">
                    <li v-for="item in section.items" :key="item.slug">
                        <RouterLink
                            :to="{ name: 'documentation', params: { version, page: item.slug } }"
                            class="-ml-3 block rounded-r py-1 pl-3 text-[13px] leading-6 text-docs-muted transition-colors hover:text-docs-accent hover:no-underline [&.router-link-exact-active]:bg-docs-soft [&.router-link-exact-active]:font-semibold [&.router-link-exact-active]:text-docs-accent">
                            {{ item.title }}
                        </RouterLink>
                    </li>
                </ul>
            </details>
        </nav>
    </aside>
</template>
