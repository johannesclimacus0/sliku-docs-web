import axios, { type AxiosRequestConfig } from 'axios'

type ApiResponse<T> = {
    data?: T
    error?: {
        message: string
    }
}

export class ApiRequestError extends Error {
    constructor(
        public status: number,
        message: string,
    ) {
        super(message)
        this.name = 'ApiRequestError'
    }
}

const http = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL?.replace(/\/+$/, '') || '/api',
    timeout: 15_000,
    headers: { Accept: 'application/json' },
})

export async function request<T>(config: AxiosRequestConfig): Promise<T> {
    try {
        const response = await http.request<ApiResponse<T>>(config)
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

        const payload = error.response.data
        const message = payload?.error?.message || `HTTP ${error.response.status}`
        throw new ApiRequestError(error.response.status, message)
    }
}
