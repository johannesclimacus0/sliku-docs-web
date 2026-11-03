import { onBeforeUnmount, onMounted, ref } from 'vue'

type Theme = 'light' | 'dark'
const storageKey = 'sliku-docs-theme'

function savedTheme(): Theme | null {
    try {
        const saved = localStorage.getItem(storageKey)
        return saved === 'light' || saved === 'dark' ? saved : null
    } catch {
        return null
    }
}

export function useDocsTheme() {
    const system = window.matchMedia('(prefers-color-scheme: dark)')
    let preference = savedTheme()
    const theme = ref<Theme>(preference ?? (system.matches ? 'dark' : 'light'))

    function apply(value: Theme) {
        theme.value = value
        document.documentElement.dataset.docsTheme = value
        document.documentElement.style.backgroundColor = value === 'dark' ? '#101b20' : '#ffffff'
    }

    function toggleTheme() {
        const next = theme.value === 'dark' ? 'light' : 'dark'
        preference = next
        apply(next)
        try {
            localStorage.setItem(storageKey, next)
        } catch {}
    }

    function systemChanged(event: MediaQueryListEvent) {
        if (!preference) apply(event.matches ? 'dark' : 'light')
    }

    function preferenceChanged(event: StorageEvent) {
        if (event.key === storageKey || event.key === null) {
            preference = savedTheme()
            apply(preference ?? (system.matches ? 'dark' : 'light'))
        }
    }

    onMounted(() => {
        apply(theme.value)
        system.addEventListener('change', systemChanged)
        window.addEventListener('storage', preferenceChanged)
    })

    onBeforeUnmount(() => {
        system.removeEventListener('change', systemChanged)
        window.removeEventListener('storage', preferenceChanged)
        document.documentElement.style.removeProperty('background-color')
    })

    return { theme, toggleTheme }
}
