import { request } from '../http'

export type DocumentationHeading = {
    id: string
    title: string
    level: 2 | 3
}

export type DocumentationPage = {
    version: string
    slug: string
    title: string
    html: string
    headings: DocumentationHeading[]
}

export type DocumentationSection = {
    title: string
    items: {
        title: string
        slug: string
    }[]
}

export type DocumentationVersions = {
    defaultVersion: string
    versions: { id: string; label: string }[]
}

let versionCatalog: Promise<DocumentationVersions> | undefined

export function getDocumentationVersions(): Promise<DocumentationVersions> {
    versionCatalog ??= request<DocumentationVersions>({ method: 'GET', url: '/docs/versions' }).catch((cause: unknown) => {
        versionCatalog = undefined
        throw cause
    })
    return versionCatalog
}

const pages = new Map<string, DocumentationPage>()
const navigation = new Map<string, DocumentationSection[]>()

export async function getDocumentationNavigation(version: string): Promise<DocumentationSection[]> {
    const cached = navigation.get(version)
    if (cached) return cached

    const sections = await request<DocumentationSection[]>({
        method: 'GET',
        url: `/docs/${encodeURIComponent(version)}/navigation`,
    })

    navigation.set(version, sections)

    return sections
}

export async function getDocumentationPage(version: string, slug: string): Promise<DocumentationPage> {
    const key = `${version}/${slug}`
    const cached = pages.get(key)
    if (cached) return cached

    const article = await request<DocumentationPage>({
        method: 'GET',
        url: `/docs/${encodeURIComponent(version)}/${encodeURIComponent(slug)}`,
    })

    pages.set(key, article)

    return article
}
