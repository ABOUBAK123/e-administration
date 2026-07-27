import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: ['resources/views/**', 'resources/css/**', 'resources/js/**'],
        }),
        tailwindcss(),
    ],
    server: {
        host: 'localhost',
        port: 5173,
        watch: {
            ignored: [
                '**/storage/**',
                '**/bootstrap/cache/**',
                '**/vendor/**',
                '**/node_modules/**',
                '**/*.log',
                '**/*.lock',
            ],
        },
    },
});
