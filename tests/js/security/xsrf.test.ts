import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const sources = (directory: string): string[] =>
    readdirSync(directory).flatMap((name) => {
        const path = join(directory, name);

        return statSync(path).isDirectory() ? sources(path) : /\.(ts|vue)$/.test(name) ? [path] : [];
    });

describe('the XSRF cookie reader', () => {
    it('lives in resources/js/xsrf.ts and nowhere else', () => {
        const readers = sources('resources/js').filter((path) => readFileSync(path, 'utf8').includes('XSRF-TOKEN='));

        expect(readers).toEqual(['resources/js/xsrf.ts']);
    });
});
