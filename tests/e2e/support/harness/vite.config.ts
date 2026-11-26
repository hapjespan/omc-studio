import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import { fixOpenPencilWorkers } from '../../../../vite.config.js';

const here = dirname(fileURLToPath(import.meta.url));
const headers = { 'Content-Security-Policy': "default-src 'self'; script-src 'self' 'wasm-unsafe-eval'; style-src 'self'; img-src 'self'" };

export default defineConfig({
    root: here,
    publicDir: resolve(here, '../../../../public'),
    plugins: [fixOpenPencilWorkers(), vue(), tailwindcss()],
    build: { outDir: resolve(here, '../../../../.scratch/harness'), emptyOutDir: true },
    preview: { host: '127.0.0.1', port: Number(process.env.E2E_HARNESS_PORT ?? 4187), strictPort: true, headers },
});
