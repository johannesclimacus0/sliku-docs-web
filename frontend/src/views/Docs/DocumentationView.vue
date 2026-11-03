<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
    getDocumentationNavigation,
    getDocumentationPage,
    getDocumentationVersions,
    type DocumentationPage,
    type DocumentationSection,
    type DocumentationVersions,
} from '@/services/Docs/documentation'
import DocsSidebar from '@/components/Docs/DocsSidebar.vue'
import DocsOutline from '@/components/Docs/DocsOutline.vue'
import vCodeBlocks from '@/directives/codeBlocks'
import { useDocsTheme } from '@/composables/useDocsTheme'
import { useActiveHeading } from '@/composables/useActiveHeading'
import DocsNotFound from '@/components/Docs/DocsNotFound.vue'
import DocsError from '@/components/Docs/DocsError.vue'
import { ApiRequestError } from '@/services/http'
import vDocsLinks from '@/directives/docsLinks'

const route = useRoute()
const router = useRouter()
const { theme, toggleTheme } = useDocsTheme()
const article = ref<DocumentationPage | null>(null)
const error = ref('')
const errorStatus = ref<number | null>(null)
const articleElement = ref<HTMLElement | null>(null)
const outlineItems = computed(() => (error.value ? [] : (article.value?.headings ?? [])))
const activeHeading = useActiveHeading(articleElement, outlineItems)

const sections = ref<DocumentationSection[]>([])
const navigationLoading = ref(false)
const navigationError = ref('')
const catalog = ref<DocumentationVersions | null>(null)
const catalogError = ref('')
const version = computed(() => String(route.params.version ?? ''))
const knownVersion = computed(() => catalog.value?.versions.some((item) => item.id === version.value) ?? false)

async function loadVersions() {
    try {
        catalog.value = await getDocumentationVersions()
    } catch (cause) {
        catalogError.value = cause instanceof Error ? cause.message : 'Не удалось загрузить версии'
    }
}

void loadVersions()

// Когда сервер вернет список версий, главная откроет версию по умолчанию.
watch([() => route.name, catalog], ([name, versions]) => {
    if (name !== 'home' || !versions) return

    void router.replace({
        name: 'documentation',
        params: { version: versions.defaultVersion, page: 'installation' },
        query: route.query,
        hash: route.hash,
    })
})

function changeVersion(event: Event) {
    const selected = (event.target as HTMLSelectElement).value
    if (selected === version.value || !catalog.value?.versions.some((item) => item.id === selected)) return

    void router.push({
        name: 'documentation',
        params: { version: selected, page: route.params.page || 'installation' },
        query: route.query,
        hash: route.hash,
    })
}

watch(
    version,
    async (version, _, onCleanup) => {
        if (!version) return
        let active = true
        onCleanup(() => {
            active = false
        })

        sections.value = []
        navigationLoading.value = true
        navigationError.value = ''

        try {
            const result = await getDocumentationNavigation(version)
            if (active) sections.value = result
        } catch (cause) {
            if (active) {
                navigationError.value = cause instanceof Error ? cause.message : 'Не удалось загрузить меню'
            }
        } finally {
            if (active) navigationLoading.value = false
        }
    },
    { immediate: true },
)

watch(
    () => [version.value, String(route.params.page ?? '')] as const,
    async ([version, page], _, onCleanup) => {
        if (!version || !page) return
        let active = true

        onCleanup(() => {
            active = false
        })

        error.value = ''
        errorStatus.value = null

        try {
            const result = await getDocumentationPage(version, page)

            if (active) {
                article.value = result
                document.title = result.title
            }
        } catch (cause) {
            if (active) {
                error.value = cause instanceof Error ? cause.message : 'Не удалось загрузить статью'
                errorStatus.value = cause instanceof ApiRequestError ? cause.status : null
                document.title = errorStatus.value === 404 ? 'Статья не найдена' : 'Ошибка загрузки'
            }
        }
    },
    { immediate: true },
)
</script>
<template>
    <div class="docs-page min-h-dvh bg-docs-background text-docs-ink antialiased">
        <div
            class="mx-auto w-full max-w-[1440px] px-6 md:grid md:grid-cols-[240px_minmax(0,1fr)] md:gap-x-12 md:px-8 xl:grid-cols-[240px_minmax(0,1fr)_224px] xl:gap-x-16 min-[90rem]:grid-cols-[272px_minmax(0,1fr)_224px] min-[90rem]:gap-x-24">
            <div class="flex flex-col border-b border-docs-line md:sticky md:top-0 md:h-dvh md:self-start md:border-r md:border-b-0">
                <div class="min-h-0 max-h-64 flex-1 overflow-y-auto py-7 md:max-h-none md:pt-12 md:pr-6 md:pb-8">
                    <p v-if="navigationLoading" class="text-[13px] leading-relaxed text-docs-muted">Загружаем меню…</p>
                    <p v-else-if="navigationError" class="text-[13px] leading-relaxed text-docs-error-ink">
                        Не удалось загрузить меню: {{ navigationError }}
                    </p>
                    <DocsSidebar v-else-if="version" :version="version" :sections="sections" />
                </div>
                <div class="shrink-0 space-y-3 border-t border-docs-line py-5 text-xs md:mr-6">
                    <div class="flex items-center justify-between gap-4">
                        <label for="docs-version" class="text-docs-muted">Версия</label>
                        <select
                            id="docs-version"
                            :value="version"
                            :disabled="!catalog"
                            class="min-w-0 max-w-44 rounded border border-docs-line bg-docs-background px-2 py-1 text-xs text-docs-accent focus:border-miku"
                            @change="changeVersion">
                            <option v-if="!knownVersion" :value="version" disabled>{{ version || 'Загрузка…' }}</option>
                            <option v-for="item in catalog?.versions ?? []" :key="item.id" :value="item.id">{{ item.label }}</option>
                        </select>
                    </div>
                    <div v-if="catalogError" class="text-docs-error-ink">
                        <p class="mb-2 text-xs">Не удалось получить список версий: {{ catalogError }}</p>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-docs-muted">Тема</span>
                        <button
                            type="button"
                            :title="theme === 'dark' ? 'Включить светлую тему' : 'Включить тёмную тему'"
                            class="flex min-w-25 items-center justify-center gap-2 rounded border border-docs-line bg-docs-soft px-2 py-1 text-docs-accent hover:border-miku"
                            @click="toggleTheme">
                            <svg
                                v-if="theme === 'dark'"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-3.5 w-3.5">
                                <path
                                    d="M20.985 12.486a9 9 0 1 1-9.473-9.472c.405-.022.617.46.402.803a6 6 0 0 0 8.268 8.268c.344-.215.825-.004.803.401" />
                            </svg>
                            <svg
                                v-else
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                class="h-3.5 w-3.5">
                                <circle cx="12" cy="12" r="4" />
                                <path d="M12 2v2" />
                                <path d="M12 20v2" />
                                <path d="m4.93 4.93 1.41 1.41" />
                                <path d="m17.66 17.66 1.41 1.41" />
                                <path d="M2 12h2" />
                                <path d="M20 12h2" />
                                <path d="m6.34 17.66-1.41 1.41" />
                                <path d="m19.07 4.93-1.41 1.41" />
                            </svg>
                            {{ theme === 'dark' ? 'Тёмная' : 'Светлая' }}
                        </button>
                    </div>
                </div>
            </div>

            <main class="min-w-0 pt-10 pb-24 md:pt-12 md:pb-32">
                <div class="relative max-w-[688px]">
                    <DocsError
                        v-if="!version && catalogError"
                        title="Не удалось открыть документацию"
                        message="Не удалось получить список версий. Обновите страницу позже." />
                    <div v-if="error" :data-docs-key="`${version}/${String(route.params.page)}`">
                        <DocsNotFound
                            v-if="errorStatus === 404"
                            :title="catalog && !knownVersion ? 'Версия не найдена' : 'Статья не найдена'"
                            :description="
                                catalog && !knownVersion
                                    ? `Версия «${version}» недоступна. Выберите другую версию или откройте руководство с начала.`
                                    : `Статья отсутствует в версии «${version}». Выберите другую статью в меню или откройте эту версию с начала.`
                            "
                            :version="knownVersion ? version : undefined" />
                        <DocsError v-else title="Не удалось загрузить статью" :message="error" />
                    </div>
                    <article
                        v-if="article && !error"
                        ref="articleElement"
                        v-code-blocks="article.html"
                        v-docs-links="router"
                        :data-docs-key="`${article.version}/${article.slug}`"
                        class="docs-content"
                        v-html="article.html" />
                </div>
            </main>

            <DocsOutline
                :items="outlineItems"
                :active-id="activeHeading"
                class="sticky top-12 mt-12 hidden max-h-[calc(100dvh-6rem)] self-start overflow-y-auto xl:block" />
        </div>
    </div>
</template>
