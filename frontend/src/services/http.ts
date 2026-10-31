import { shallowRef } from 'vue'
import axios, { type AxiosRequestConfig } from 'axios'

type ApiResponse<T> = {
    data?: T
    error?: {
        message: string
        details?: Record<string, string[]>
    }
}

export class ApiRequestError extends Error {
    constructor(
        public status: number,
        message: string,
        public requestId?: string,
    ) {
        super(message)
        this.name = 'ApiRequestError'
    }
}

export const http = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL?.replace(/\/+$/, '') || '/api',
    withCredentials: true,
    timeout: 15_000,
    headers: { Accept: 'application/json' },
})

export const lastRequest = shallowRef<{
    method: string
    path: string
    status: number
    requestId: string
    duration: string
} | null>(null)

export async function request<T>(config: AxiosRequestConfig): Promise<T> {
    try {
        const response = await http.request<ApiResponse<T>>(config)
        lastRequest.value = {
            method: (config.method ?? 'GET').toUpperCase(),
            path: config.url ?? '',
            status: response.status,
            requestId: String(response.headers['x-request-id'] ?? ''),
            duration: String(response.headers['x-response-time'] ?? ''),
        }

        if (response.data?.data === undefined) {
            throw new ApiRequestError(response.status, 'Сервер вернул ответ без данных')
        }

        return response.data.data
    } catch (error) {
        if (!axios.isAxiosError<ApiResponse<unknown>>(error)) {
            throw error
        }

        if (!error.response) {
            const timedOut = error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT'
            throw new ApiRequestError(0, timedOut ? 'Превышено время ожидания ответа сервера' : 'Не удалось подключиться к серверу')
        }

        lastRequest.value = {
            method: (config.method ?? 'GET').toUpperCase(),
            path: config.url ?? '',
            status: error.response.status,
            requestId: String(error.response.headers['x-request-id'] ?? ''),
            duration: String(error.response.headers['x-response-time'] ?? ''),
        }

        const payload = error.response.data
        const details = Object.values(payload?.error?.details ?? {})
            .flat()
            .join('; ')
        const message = details || payload?.error?.message || `HTTP ${error.response.status}`
        const requestId = error.response.headers['x-request-id']

        throw new ApiRequestError(error.response.status, message, typeof requestId === 'string' ? requestId : undefined)
    }
}
