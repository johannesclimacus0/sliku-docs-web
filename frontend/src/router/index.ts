import { createRouter, createWebHistory } from 'vue-router'
import DocumentationView from '@/views/Docs/DocumentationView.vue'
import NotFoundView from '@/views/Docs/NotFoundView.vue'
import { scrollBehavior } from './scroll'

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    scrollBehavior,
    routes: [
        {
            path: '/',
            name: 'home',
            component: DocumentationView,
        },
        {
            path: '/docs',
            redirect: (to) => ({ name: 'home', query: to.query, hash: to.hash }),
        },
        {
            path: '/docs/:version',
            redirect: (to) => ({
                name: 'documentation',
                params: { version: to.params.version, page: 'installation' },
                query: to.query,
                hash: to.hash,
            }),
        },
        {
            path: '/docs/:version/:page',
            name: 'documentation',
            component: DocumentationView,
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: NotFoundView,
        },
    ],
})

router.afterEach((to, from) => {
    if (to.name === 'not-found') document.title = 'Страница не найдена'
    else if (to.path !== from.path) document.title = 'Документация'
})

export default router
