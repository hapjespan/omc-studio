import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vite';

export default defineConfig({
    root: import.meta.dirname,
    plugins: [
        vue(),
        {
            name: 'fix-worker-extension',
            enforce: 'pre',
            transform(code, id) {
                if (!id.includes('@open-pencil/')) return null;
                const fixed = code.replace(/new URL\((['"])(\.[^'"]*)\.ts\1, import\.meta\.url\)/g, 'new URL($1$2.js$1, import.meta.url)');
                return fixed === code ? null : fixed;
            },
        },
    ],
    build: { outDir: 'dist-page', emptyOutDir: true, target: 'es2022', rollupOptions: { input: 'page.html' } },
});
