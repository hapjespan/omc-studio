import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const proofEntry = fileURLToPath(new URL('./main.ts', import.meta.url));

export default defineConfig({
    root: fileURLToPath(new URL('../..', import.meta.url)),
    plugins: [
        {
            name: 'resolve-from-proof',
            async resolveId(id, importer, options) {
                if (!/^(@open-pencil|@fontsource|@expo-google-fonts)\//.test(id)) return null;
                return this.resolve(id, proofEntry, { ...options, skipSelf: true });
            },
        },
    ],
    test: { include: ['tests/js/proof/**/*.test.ts'] },
});
