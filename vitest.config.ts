import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['tests/js/**/*.test.ts'],
        exclude: ['tests/js/proof/**', 'node_modules/**'],
    },
});
