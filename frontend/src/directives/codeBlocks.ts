import type { Directive } from 'vue'

const cleanups = new WeakMap<HTMLElement, () => void>()
const copyStates = {
    idle: {
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="14" height="14" x="8" y="8" rx="2" ry="2" /><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2" /></svg>`,
        title: 'Скопировать код',
    },
    copied: {
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>`,
        title: 'Код скопирован',
    },
    error: {
        icon: `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3" /><path d="M12 9v4" /><path d="M12 17h.01" /></svg>`,
        title: 'Браузер не разрешил копирование. Выделите код вручную.',
    },
}

function enhanceCodeBlocks(root: HTMLElement) {
    const disposeBlocks: (() => void)[] = []

    root.querySelectorAll<HTMLPreElement>('pre').forEach((pre) => {
        const code = pre.querySelector('code')
        if (!code || pre.parentElement?.classList.contains('docs-code-block')) return

        const source = code.textContent ?? ''
        const language = [...code.classList].find((name) => name.startsWith('language-'))?.slice(9)
        const block = document.createElement('div')
        block.className = 'docs-code-block'
        const toolbar = document.createElement('div')
        toolbar.className = 'docs-code-toolbar'
        const label = document.createElement('span')
        label.className = 'docs-code-language'
        label.textContent = language || 'Код'
        const button = document.createElement('button')
        button.type = 'button'
        button.className = 'docs-copy-button'
        const setCopyState = (state: keyof typeof copyStates) => {
            button.innerHTML = copyStates[state].icon
            button.title = copyStates[state].title
            button.dataset.copyState = state
        }
        setCopyState('idle')
        toolbar.append(label, button)
        pre.before(block)
        block.append(toolbar, pre)

        let timer: ReturnType<typeof setTimeout> | undefined
        let disposed = false
        const copy = async () => {
            if (timer) clearTimeout(timer)
            button.disabled = true
            try {
                await navigator.clipboard.writeText(source)
                if (disposed) return
                setCopyState('copied')
            } catch {
                if (disposed) return
                setCopyState('error')
            } finally {
                if (!disposed) {
                    button.disabled = false
                    timer = setTimeout(() => {
                        setCopyState('idle')
                    }, 2000)
                }
            }
        }

        button.addEventListener('click', copy)
        disposeBlocks.push(() => {
            disposed = true
            if (timer) clearTimeout(timer)
            button.removeEventListener('click', copy)
        })

        if (language) {
            void (async () => {
                try {
                    const { highlightCode } = await import('@/services/Docs/syntaxHighlighting')
                    if (disposed) return
                    const html = await highlightCode(source, language)
                    if (!html || disposed || !code.isConnected) return

                    const template = document.createElement('template')
                    template.innerHTML = html
                    const highlighted = template.content.querySelector('code')
                    if (!highlighted) return

                    code.innerHTML = highlighted.innerHTML
                    pre.dataset.highlighted = 'true'
                } catch {}
            })()
        }
    })

    cleanups.set(root, () => disposeBlocks.forEach((dispose) => dispose()))
}

const codeBlocks: Directive<HTMLElement, string> = {
    mounted: enhanceCodeBlocks,
    beforeUpdate(root, binding) {
        if (binding.value !== binding.oldValue) cleanups.get(root)?.()
    },
    updated(root, binding) {
        if (binding.value !== binding.oldValue) enhanceCodeBlocks(root)
    },
    beforeUnmount(root) {
        cleanups.get(root)?.()
        cleanups.delete(root)
    },
}

export default codeBlocks
