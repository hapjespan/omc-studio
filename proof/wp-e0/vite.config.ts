import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        lib: {
            entry: 'main.ts',
            formats: ['es'],
            fileName: 'proof',
        },
    },
});
