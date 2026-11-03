import { nextTick } from 'vue'
import type { RouterScrollBehavior } from 'vue-router'

let pendingScroll: AbortController | undefined

function waitForArticle(key: string, signal: AbortSignal): Promise<boolean> {
    const isReady = () => [...document.querySelectorAll<HTMLElement>('[data-docs-key]')].some((node) => node.dataset.docsKey === key)
    if (isReady()) return Promise.resolve(true)

    return new Promise((resolve) => {
        const finish = (ready: boolean) => {
            observer.disconnect()
            clearTimeout(timeout)
            signal.removeEventListener('abort', abort)
            resolve(ready)
        }
        const abort = () => finish(false)
        const observer = new MutationObserver(() => {
            if (isReady()) finish(true)
        })
        const timeout = setTimeout(() => finish(false), 16_000)
        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-docs-key'] })
        signal.addEventListener('abort', abort, { once: true })
        if (signal.aborted) finish(false)
    })
}

export const scrollBehavior: RouterScrollBehavior = async (to, from, savedPosition) => {
    pendingScroll?.abort()
    const controller = new AbortController()
    pendingScroll = controller

    if (to.name === 'documentation') {
        const ready = await waitForArticle(`${String(to.params.version)}/${String(to.params.page)}`, controller.signal)
        if (!ready) return false
    }

    await nextTick()
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()))
    if (controller.signal.aborted) return false

    if (savedPosition) return { ...savedPosition, behavior: 'auto' }

    if (to.hash) {
        let id: string
        try {
            id = decodeURIComponent(to.hash.slice(1))
        } catch {
            return { top: 0, behavior: 'auto' }
        }

        const target = document.getElementById(id)
        if (target?.closest('.docs-content')) {
            const smooth = to.path === from.path && !window.matchMedia('(prefers-reduced-motion: reduce)').matches
            return { el: target, top: 32, behavior: smooth ? 'smooth' : 'auto' }
        }
    }

    return { top: 0, behavior: 'auto' }
}
