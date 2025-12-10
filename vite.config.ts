import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

export default defineConfig({
    plugins: [vue(), tailwindcss()],
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/js/app.ts'),
            name: 'LaraviltAI',
            fileName: (format) => `ai.${format}.js`,
        },
        rollupOptions: {
            external: ['vue', '@inertiajs/vue3'],
            output: {
                globals: {
                    vue: 'Vue',
                    '@inertiajs/vue3': 'Inertia',
                },
            },
        },
        outDir: 'dist',
        emptyOutDir: true,
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
})
