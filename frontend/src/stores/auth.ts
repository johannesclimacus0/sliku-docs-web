import { defineStore } from 'pinia'
import { ref } from 'vue'
import { authService, type User } from '@/services/auth'
import { ApiRequestError } from '@/services/http'

export const useAuthStore = defineStore('auth', () => {
    const accessToken = ref<string | null>(null)
    const user = ref<User | null>(null)
    const ready = ref(false)
    let refreshing: Promise<boolean> | null = null

    function clear(): void {
        accessToken.value = null
        user.value = null
    }

    function refresh(): Promise<boolean> {
        if (refreshing !== null) {
            return refreshing
        }

        refreshing = authService
            .refresh()
            .then((result) => {
                accessToken.value = result.access_token
                return true
            })
            .catch((error: unknown) => {
                if (error instanceof ApiRequestError && error.status === 401) {
                    clear()
                    return false
                }

                throw error
            })
            .finally(() => {
                refreshing = null
            })

        return refreshing
    }

    async function loadMe(retryAfterRefresh = true): Promise<boolean> {
        if (accessToken.value === null) {
            return false
        }

        try {
            user.value = await authService.me(accessToken.value)
            return true
        } catch (error) {
            if (error instanceof ApiRequestError && error.status === 401) {
                if (retryAfterRefresh && (await refresh())) {
                    return loadMe(false)
                }

                clear()
            }

            throw error
        }
    }

    async function restore(): Promise<void> {
        try {
            if (await refresh()) {
                await loadMe(false)
            }
        } catch {
            clear()
        } finally {
            ready.value = true
        }
    }

    async function register(email: string, password: string): Promise<void> {
        const result = await authService.register(email, password)
        accessToken.value = result.access_token
        await loadMe(false)
    }

    async function login(email: string, password: string, remember: boolean): Promise<void> {
        const result = await authService.login(email, password, remember)
        accessToken.value = result.access_token
        await loadMe(false)
    }

    async function logout(): Promise<void> {
        await authService.logout()
        clear()
    }

    async function withToken<T>(callback: (token: string) => Promise<T>): Promise<T> {
        if (accessToken.value === null && !(await refresh())) {
            throw new ApiRequestError(401, 'Необходимо войти в аккаунт')
        }

        const token = accessToken.value
        if (token === null) throw new ApiRequestError(401, 'Необходимо войти в аккаунт')

        try {
            return await callback(token)
        } catch (error) {
            if (!(error instanceof ApiRequestError) || error.status !== 401) throw error

            if (accessToken.value === token && !(await refresh())) throw error
            const renewed = accessToken.value
            if (renewed === null) throw error

            try {
                return await callback(renewed)
            } catch (retryError) {
                if (retryError instanceof ApiRequestError && retryError.status === 401) clear()
                throw retryError
            }
        }
    }

    return { accessToken, user, ready, register, login, logout, restore, loadMe, withToken }
})
