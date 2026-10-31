import { request } from './http'

export type UserNotification = {
    id: string
    type: string
    title: string
    body: string
    data: Record<string, unknown>
    createdAt: number
    readAt: number | null
}
export type NotificationList = {
    items: UserNotification[]
    unreadCount: number
    pagination: { page: number; perPage: number; total: number }
}
const headers = (token: string) => ({ Authorization: `Bearer ${token}` })
export const notificationService = {
    list: (token: string, page: number, unreadOnly: boolean) =>
        request<NotificationList>({
            url: '/account/notifications',
            params: { page, unreadOnly: unreadOnly ? '1' : '0' },
            headers: headers(token),
        }),
    count: (token: string) =>
        request<{ unreadCount: number }>({
            url: '/account/notifications/unread-count',
            headers: headers(token),
        }),
    read: (token: string, id: string) =>
        request<{ message: string }>({
            method: 'PATCH',
            url: `/account/notifications/${id}/read`,
            data: {},
            headers: headers(token),
        }),
    readAll: (token: string) =>
        request<{ message: string }>({
            method: 'PATCH',
            url: '/account/notifications/read-all',
            data: {},
            headers: headers(token),
        }),
    demo: (token: string) =>
        request<{ id: string; message: string }>({
            method: 'POST',
            url: '/account/notifications/demo',
            data: {},
            headers: headers(token),
        }),
}
