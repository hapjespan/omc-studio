import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { fixOpenPencilWorkers } from '../../../vite.config.js';

const plugin = fixOpenPencilWorkers();
const transform = (code: string, id: string) => (plugin.transform as (code: string, id: string) => string | null)(code, id);
const library = 'node_modules/@open-pencil/core/dist';

describe('worker URL repair of the drawing library', () => {
    it('rewrites the worker extension in library files', () => {
        const code = 'new Worker(new URL("./worker.ts", import.meta.url), { type: "module" })';
        expect(transform(code, '/x/node_modules/@open-pencil/core/dist/a.js')).toBe(code.replace('worker.ts', 'worker.js'));
    });

    it('leaves files outside the library untouched', () => {
        expect(transform('new URL("./worker.ts", import.meta.url)', '/x/resources/js/app.ts')).toBeNull();
    });

    it('repairs both published files so that the worker file they name exists', () => {
        for (const file of ['kiwi/fig/session/client.js', 'io/formats/fig/export.js']) {
            const path = resolve(library, file);
            const fixed = transform(readFileSync(path, 'utf8'), path) ?? '';
            const target = /new URL\("(\.[^"]+)", import\.meta\.url\)/.exec(fixed)?.[1] ?? '';
            expect(target.endsWith('.js'), file).toBe(true);
            expect(existsSync(resolve(dirname(path), target)), file).toBe(true);
        }
    });

    it('pins the library packages exactly', () => {
        const { dependencies } = JSON.parse(readFileSync('package.json', 'utf8'));
        expect(dependencies['@open-pencil/core']).toMatch(/^\d+\.\d+\.\d+$/);
        expect(dependencies['@open-pencil/vue']).toMatch(/^\d+\.\d+\.\d+$/);
    });
});
