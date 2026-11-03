import type { HighlighterCore, ThemeRegistration } from 'shiki/core'

const languageLoaders = {
    php: () => import('shiki/langs/php.mjs'),
    vue: () => import('shiki/langs/vue.mjs'),
    typescript: () => import('shiki/langs/typescript.mjs'),
    javascript: () => import('shiki/langs/javascript.mjs'),
    bash: () => import('shiki/langs/bash.mjs'),
    json: () => import('shiki/langs/json.mjs'),
    yaml: () => import('shiki/langs/yaml.mjs'),
    html: () => import('shiki/langs/html.mjs'),
    css: () => import('shiki/langs/css.mjs'),
    sql: () => import('shiki/langs/sql.mjs'),
    markdown: () => import('shiki/langs/markdown.mjs'),
    twig: () => import('shiki/langs/twig.mjs'),
}

type Language = keyof typeof languageLoaders

const aliases = new Map<string, Language>([
    ['ts', 'typescript'],
    ['js', 'javascript'],
    ['sh', 'bash'],
    ['shell', 'bash'],
    ['shellscript', 'bash'],
    ['yml', 'yaml'],
    ['md', 'markdown'],
])

const theme = 'miku-light'
const themeRegistration: ThemeRegistration = {
    name: theme,
    displayName: 'Miku Light',
    type: 'light',
    colors: {
        'editor.background': '#f3f9f9',
        'editor.foreground': '#3e555c',
    },
    tokenColors: [
        { scope: ['comment', 'punctuation.definition.comment'], settings: { foreground: '#7e989b', fontStyle: 'italic' } },
        { scope: ['keyword', 'storage'], settings: { foreground: '#ba3e7b' } },
        { scope: ['string'], settings: { foreground: '#27786e' } },
        { scope: ['constant'], settings: { foreground: '#94618b' } },
        { scope: ['variable'], settings: { foreground: '#3e6574' } },
        { scope: ['entity.name.function', 'support.function'], settings: { foreground: '#087f7c' } },
        { scope: ['entity.name.type', 'entity.name.class', 'support.class', 'support.type'], settings: { foreground: '#087f7c' } },
        { scope: ['entity.name.tag', 'punctuation.definition.tag'], settings: { foreground: '#087f7c' } },
        { scope: ['entity.other.attribute-name'], settings: { foreground: '#a04b7b' } },
        { scope: ['markup.heading'], settings: { foreground: '#087f7c', fontStyle: 'bold' } },
        { scope: ['markup.bold'], settings: { fontStyle: 'bold' } },
        { scope: ['markup.italic'], settings: { fontStyle: 'italic' } },
        { scope: ['invalid'], settings: { foreground: '#b84465' } },
    ],
}

const darkThemeRegistration: ThemeRegistration = {
    name: 'miku-dark',
    displayName: 'Miku Dark',
    type: 'dark',
    colors: {
        'editor.background': '#13242a',
        'editor.foreground': '#bacdd0',
    },
    tokenColors: [
        { scope: ['comment', 'punctuation.definition.comment'], settings: { foreground: '#7b989e', fontStyle: 'italic' } },
        { scope: ['keyword', 'storage'], settings: { foreground: '#f281b3' } },
        { scope: ['string'], settings: { foreground: '#8ed9b9' } },
        { scope: ['constant'], settings: { foreground: '#cba7ec' } },
        { scope: ['variable'], settings: { foreground: '#a8cfdd' } },
        { scope: ['entity.name.function', 'support.function'], settings: { foreground: '#52d6cb' } },
        { scope: ['entity.name.type', 'entity.name.class', 'support.class', 'support.type'], settings: { foreground: '#52d6cb' } },
        { scope: ['entity.name.tag', 'punctuation.definition.tag'], settings: { foreground: '#52d6cb' } },
        { scope: ['entity.other.attribute-name'], settings: { foreground: '#f0a1c7' } },
        { scope: ['markup.heading'], settings: { foreground: '#52d6cb', fontStyle: 'bold' } },
        { scope: ['markup.bold'], settings: { fontStyle: 'bold' } },
        { scope: ['markup.italic'], settings: { fontStyle: 'italic' } },
        { scope: ['invalid'], settings: { foreground: '#ff91ac' } },
    ],
}
const loadedLanguages = new Map<Language, Promise<void>>()
const renderedBlocks = new Map<string, string>()
let highlighterPromise: Promise<HighlighterCore> | undefined

function getHighlighter(): Promise<HighlighterCore> {
    highlighterPromise ??= Promise.all([import('shiki/core'), import('shiki/engine/javascript')])
        .then(([{ createHighlighterCore }, { createJavaScriptRegexEngine }]) =>
            createHighlighterCore({
                themes: [themeRegistration, darkThemeRegistration],
                langs: [],
                engine: createJavaScriptRegexEngine(),
            }),
        )
        .catch((cause) => {
            highlighterPromise = undefined
            throw cause
        })

    return highlighterPromise
}

export async function highlightCode(source: string, requestedLanguage: string): Promise<string | null> {
    const requested = requestedLanguage.toLowerCase()
    const normalized = aliases.get(requested) ?? requested
    if (!Object.hasOwn(languageLoaders, normalized)) return null

    const language = normalized as Language
    const key = `${language}\0${source}`
    const cached = renderedBlocks.get(key)
    if (cached !== undefined) return cached

    const highlighter = await getHighlighter()
    let loading = loadedLanguages.get(language)
    if (!loading) {
        loading = languageLoaders[language]()
            .then((grammar) => highlighter.loadLanguage(grammar.default))
            .catch((cause) => {
                loadedLanguages.delete(language)
                throw cause
            })
        loadedLanguages.set(language, loading)
    }
    await loading

    const html = highlighter.codeToHtml(source, {
        lang: language,
        themes: { light: theme, dark: 'miku-dark' },
        defaultColor: false,
    })
    if (renderedBlocks.size >= 100) {
        const oldest = renderedBlocks.keys().next().value
        if (oldest !== undefined) renderedBlocks.delete(oldest)
    }
    renderedBlocks.set(key, html)

    return html
}
