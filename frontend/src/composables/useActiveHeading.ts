import { ref, watch, type Ref } from 'vue'
import type { DocumentationHeading } from '@/services/Docs/documentation'

export function useActiveHeading(root: Ref<HTMLElement | null>, headings: Ref<DocumentationHeading[]>) {
    const activeHeading = ref('')

    watch(
        [root, headings],
        ([element, items], _, onCleanup) => {
            activeHeading.value = ''
            if (!element || !items.length) return

            const ids = new Set(items.map((item) => item.id))
            const targets = [...element.querySelectorAll<HTMLElement>('h2[id], h3[id]')].filter((heading) => ids.has(heading.id))
            let frame: number | undefined

            const update = () => {
                frame = undefined
                let current = ''
                for (const heading of targets) {
                    if (heading.getBoundingClientRect().top > 64) break
                    current = heading.id
                }

                const atBottom = window.scrollY > 0 && window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 2
                if (atBottom) current = targets.at(-1)?.id ?? current
                activeHeading.value = current
            }

            const schedule = () => {
                frame ??= requestAnimationFrame(update)
            }

            window.addEventListener('scroll', schedule, { passive: true })
            window.addEventListener('resize', schedule)
            const observer = new ResizeObserver(schedule)
            observer.observe(element)
            schedule()

            onCleanup(() => {
                window.removeEventListener('scroll', schedule)
                window.removeEventListener('resize', schedule)
                observer.disconnect()
                if (frame !== undefined) cancelAnimationFrame(frame)
            })
        },
        { immediate: true, flush: 'post' },
    )

    return activeHeading
}
