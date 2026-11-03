import type { Directive } from 'vue'
import type { Router } from 'vue-router'

const listeners = new WeakMap<HTMLElement, (event: MouseEvent) => void>()

const docsLinks: Directive<HTMLElement, Router> = {
    mounted(root, binding) {
        const navigate = (event: MouseEvent) => {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return
            if (!(event.target instanceof Element)) return

            const link = event.target.closest<HTMLAnchorElement>('a[href]')
            if (!link || !root.contains(link) || link.hasAttribute('download')) return
            if (link.target && link.target.toLowerCase() !== '_self') return
            if (link.relList.contains('external')) return

            const url = new URL(link.href, window.location.href)
            if (url.origin !== window.location.origin || (url.pathname !== '/' && !/^\/docs(?:\/|$)/.test(url.pathname))) return

            event.preventDefault()
            void binding.value.push(url.pathname + url.search + url.hash)
        }

        root.addEventListener('click', navigate)
        listeners.set(root, navigate)
    },
    beforeUnmount(root) {
        const navigate = listeners.get(root)
        if (navigate) root.removeEventListener('click', navigate)
        listeners.delete(root)
    },
}

export default docsLinks
