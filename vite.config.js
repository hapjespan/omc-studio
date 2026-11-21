import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export function fixOpenPencilWorkers() {
    return {
        name: 'fix-open-pencil-worker-extension',
        enforce: 'pre',
        transform(code, id) {
            if (!id.includes('@open-pencil/')) return null;
            const fixed = code.replace(/new URL\((['"])(\.[^'"]*)\.ts\1, import\.meta\.url\)/g, 'new URL($1$2.js$1, import.meta.url)');
            return fixed === code ? null : fixed;
        },
    };
}

export default defineConfig({
    plugins: [
        fixOpenPencilWorkers(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
