import { request } from './http'

export type User = {
    id: number
    email: string
    name: string
    bio: string
    createdAt: string
    updatedAt: string
    role: 'member' | 'admin'
    avatarUrl: string | null
    emailVerifiedAt: string | null
    emailVerificationRequired: boolean
}

export type TokenResponse = {
    access_token: string
    token_type: string
    expires_in: number
}

export const authService = {
    uploadAvatar(file: File, accessToken: string): Promise<{ avatarUrl: string }> {
        const data = new FormData()
        data.append('avatar', file)
        return request({
            method: 'POST',
            url: '/auth/avatar',
            data,
            headers: { Authorization: `Bearer ${accessToken}` },
        })
    },

    deleteAvatar(accessToken: string): Promise<{ avatarUrl: null }> {
        return request({
            method: 'DELETE',
            url: '/auth/avatar',
            headers: { Authorization: `Bearer ${accessToken}` },
        })
    },

    forgotPassword(email: string): Promise<{ message: string }> {
        return request({ method: 'POST', url: '/auth/forgot-password', data: { email } })
    },

    resetPassword(userId: number, token: string, password: string): Promise<{ message: string }> {
        return request({
            method: 'POST',
            url: '/auth/reset-password',
            data: { userId, token, password },
        })
    },

    verifyEmail(userId: number, token: string): Promise<{ message: string }> {
        return request({ method: 'POST', url: '/auth/email/verify', data: { userId, token } })
    },

    resendVerification(accessToken: string): Promise<{ message: string }> {
        return request({
            method: 'POST',
            url: '/auth/email/verification-notification',
            data: {},
            headers: { Authorization: `Bearer ${accessToken}` },
        })
    },

    register(email: string, password: string): Promise<TokenResponse> {
        return request({ method: 'POST', url: '/auth/register', data: { email, password } })
    },

    login(email: string, password: string, remember: boolean): Promise<TokenResponse> {
        return request({ method: 'POST', url: '/auth/login', data: { email, password, remember } })
    },

    refresh(): Promise<TokenResponse> {
        return request({ method: 'POST', url: '/auth/refresh', data: {} })
    },

    logout(): Promise<{ message: string }> {
        return request({ method: 'POST', url: '/auth/logout', data: {} })
    },

    me(accessToken: string): Promise<User> {
        return request({
            method: 'GET',
            url: '/auth/me',
            headers: { Authorization: `Bearer ${accessToken}` },
        })
    },
}
