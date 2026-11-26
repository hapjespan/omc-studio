import vue from '@vitejs/plugin-vue';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [vue()],
    test: {
        include: ['tests/js/**/*.test.ts'],
        exclude: ['tests/js/proof/**', 'node_modules/**'],
        testTimeout: 30000,
    },
});
