import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

const here = dirname(fileURLToPath(import.meta.url));
const headers = { 'Content-Security-Policy': "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'" };

export default defineConfig({
    root: here,
    publicDir: resolve(here, '../../../../public'),
    plugins: [vue(), tailwindcss()],
    build: { outDir: resolve(here, '../../../../.scratch/harness'), emptyOutDir: true },
    preview: { host: '127.0.0.1', port: 4187, strictPort: true, headers },
});
