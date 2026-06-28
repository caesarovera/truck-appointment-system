import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// Konfigurasi test terpisah dari vite.config.js: hindari laravel-vite-plugin
// (butuh manifest/server) dan pakai jsdom untuk komponen Vue.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        globals: true,
        include: ['tests/js/**/*.test.ts'],
    },
});
