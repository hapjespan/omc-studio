import { readdirSync, readFileSync, statSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import registered from '../fixtures/routes.json';
import { routes } from '../../resources/js/routes';

const patterns = registered.map((uri) => new RegExp(`^${uri.replace(/\{[^}]+\?\}/g, '[^/]*').replace(/\{[^}]+\}/g, '[^/]+')}$`));

const outputs = Object.entries(routes).map(([name, build]) => {
    const args = Array.from({ length: build.length }, (_, index) => (index === 1 ? 'owners' : '7'));

    return [name, String((build as (...values: string[]) => string)(...args)).split('?')[0]] as const;
});

const sources = (dir: string): string[] =>
    readdirSync(dir).flatMap((entry) => {
        const path = `${dir}/${entry}`;

        return statSync(path).isDirectory() ? sources(path) : /\.(vue|ts)$/.test(entry) ? [path] : [];
    });

describe('routes.ts against the registered routes', () => {
    it.each(outputs)('%s builds a path the server has', (_name, path) => {
        expect(patterns.some((pattern) => pattern.test(path)), path).toBe(true);
    });

    it('is used by every page instead of a hard-coded path', () => {
        const fixed = outputs.filter(([, path]) => !path.includes('7') && path !== '/').map(([, path]) => path);
        const offenders = sources('resources/js')
            .filter((file) => !file.endsWith('resources/js/routes.ts') && !file.endsWith('resources/js/shell/tabs.ts'))
            .flatMap((file) => {
                const source = readFileSync(file, 'utf8');

                return fixed.filter((path) => new RegExp(`['"\`]${path}(['"\`?])`).test(source)).map((path) => `${file} ${path}`);
            });

        expect(offenders).toEqual([]);
    });
});
