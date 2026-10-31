import { request } from './http'
import type { User } from './auth'
export type Activity = { id: string; action: string; created_at: number }
export type Overview = {
    user: User
    stats: { sessions: number; activities: number; verified: boolean; avatar: boolean }
    activity: Activity[]
}
export type Directory = {
    items: User[]
    pagination: { page: number; perPage: number; total: number }
    cache: { hit: boolean; ttl: number }
}
export type Delivery = {
    id: string
    event_name: string
    endpoint: string
    status: string
    attempts: number
    last_http_status: number | null
    created_at: number
}
export type Operations = {
    health: { status: string; checks: Record<string, string> }
    counts: { users: number; queued: number; failed: number; webhooks: number }
    schedule: { command: string; every: number; lastRun: number | null }[]
    deliveries: Delivery[]
    webhookEnabled: boolean
}
const headers = (token: string) => ({ Authorization: `Bearer ${token}` })
export const accountService = {
    overview: (token: string) => request<Overview>({ url: '/account/overview', headers: headers(token) }),
    operations: (token: string) => request<Operations>({ url: '/account/operations', headers: headers(token) }),
    users: (token: string, page: number, search: string) =>
        request<Directory>({ url: '/users', params: { page, search }, headers: headers(token) }),
    user: (token: string, id: number) => request<User>({ url: `/users/${id}`, headers: headers(token) }),
    profile: (token: string, id: number, name: string, bio: string) =>
        request<User>({
            method: 'PATCH',
            url: `/users/${id}`,
            data: { name, bio },
            headers: headers(token),
        }),
    role: (token: string, id: number, role: User['role']) =>
        request<User>({
            method: 'PATCH',
            url: `/users/${id}/role`,
            data: { role },
            headers: headers(token),
        }),
    demo: (token: string, action: 'job' | 'webhook' | 'schedule' | 'http', data = {}) =>
        request<Record<string, unknown> & { message?: string }>({
            method: 'POST',
            url: `/account/demo/${action}`,
            data,
            headers: headers(token),
        }),
}
export const activityLabel = (action: string) =>
    ({
        'user.registered': 'Аккаунт зарегистрирован',
        'user.verified': 'Email подтверждён',
        'user.updated': 'Профиль обновлён',
        'user.password_reset': 'Пароль восстановлен',
        'user.demo_job': 'Демонстрационное задание выполнено',
        'user.fixture_created': 'Аккаунт создан из фикстуры',
    })[action] ?? action
export const dateTime = (value: string | number) =>
    new Date(typeof value === 'number' ? value * 1000 : value).toLocaleString('ru-RU', {
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    })
