import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            name: 'overview',
            component: () => import('@/views/OverviewView.vue'),
            meta: { requiresAuth: true, title: 'Обзор' },
        },
        {
            path: '/notifications',
            name: 'notifications',
            component: () => import('@/views/NotificationsView.vue'),
            meta: { requiresAuth: true, title: 'Уведомления' },
        },
        {
            path: '/profile',
            name: 'profile',
            component: () => import('@/views/ProfileView.vue'),
            meta: { requiresAuth: true, title: 'Мой профиль' },
        },
        {
            path: '/users',
            name: 'users',
            component: () => import('@/views/UsersView.vue'),
            meta: { requiresAuth: true, admin: true, title: 'Пользователи' },
        },
        {
            path: '/operations',
            name: 'operations',
            component: () => import('@/views/OperationsView.vue'),
            meta: { requiresAuth: true, admin: true, title: 'Инструменты' },
        },
        {
            path: '/login',
            name: 'login',
            component: () => import('@/views/LoginView.vue'),
            meta: { authScreen: true, guest: true },
        },
        {
            path: '/register',
            name: 'register',
            component: () => import('@/views/RegisterView.vue'),
            meta: { authScreen: true, guest: true },
        },
        {
            path: '/forgot-password',
            name: 'forgot-password',
            component: () => import('@/views/ForgotPasswordView.vue'),
            meta: { authScreen: true },
        },
        {
            path: '/reset-password',
            name: 'reset-password',
            component: () => import('@/views/ResetPasswordView.vue'),
            meta: { authScreen: true },
        },
        {
            path: '/verify-email',
            name: 'verify-email',
            component: () => import('@/views/VerifyEmailView.vue'),
            meta: { authScreen: true },
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('@/views/NotFoundView.vue'),
            meta: { authScreen: true },
        },
    ],
})
router.beforeEach(async (to) => {
    const auth = useAuthStore()
    if (!auth.ready) await auth.restore()
    if (to.meta.requiresAuth && !auth.user) return { name: 'login', query: { redirect: to.fullPath } }
    if (to.meta.admin && auth.user?.role !== 'admin') return { name: 'overview' }
    if (to.meta.guest && auth.user) return { name: 'overview' }
})
export default router
