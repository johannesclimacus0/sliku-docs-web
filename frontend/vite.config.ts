import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import vueDevTools from 'vite-plugin-vue-devtools'

const backend = process.env.API_PROXY_TARGET || 'http://127.0.0.1:8080'

export default defineConfig({
    plugins: [vue(), tailwindcss(), vueDevTools()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url)),
        },
    },
    server: {
        proxy: {
            '/media': {
                target: backend,
                changeOrigin: true,
            },
            '/__clockwork': {
                target: backend,
                changeOrigin: true,
            },
            '/api': {
                target: backend,
                changeOrigin: true,
                rewrite: (path) => path.replace(/^\/api/, ''),
            },
        },
    },
})
