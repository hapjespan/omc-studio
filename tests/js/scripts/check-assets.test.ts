import { spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const evidence = JSON.parse(readFileSync('specs/001-omc-studio-v1/wp-e0-evidence/p6-assets.json', 'utf8'));

function scratch() {
    return mkdtempSync(join(tmpdir(), 'assets-'));
}

function write(root: string, path: string, content: string) {
    mkdirSync(join(root, path, '..'), { recursive: true });
    writeFileSync(join(root, path), content);
    return join(root, path);
}

function run(build: string, allowlist: string, sources: string) {
    return spawnSync('node', ['scripts/check-assets.mjs', build, allowlist, sources], { encoding: 'utf8' });
}

describe('check-assets with vendored strings', () => {
    it('lets every one of the 38 vendored strings of P6 pass in the build', () => {
        const urls = Object.values<string[]>(evidence.grouped).flat().map((entry) => entry.replace(/ \([^)]*\)$/, ''));
        expect(urls).toHaveLength(evidence.total);
        const root = scratch();
        write(root, 'build/vendor.js', urls.map((url) => `x("${url}");`).join('\n'));
        const result = run(join(root, 'build'), 'scripts/vendored-urls.txt', join(root, 'src'));
        expect(result.stderr).toBe('');
        expect(result.status).toBe(0);
    });

    it('fails on a URL in the build that is not a vendored string', () => {
        const root = scratch();
        write(root, 'build/app.js', 'fetch("https://cdn.example.org/x.js");');
        expect(run(join(root, 'build'), 'scripts/vendored-urls.txt', join(root, 'src')).status).toBe(1);
    });

    it('fails on a vendored string that is only a prefix of a longer URL', () => {
        const root = scratch();
        write(root, 'build/app.js', 'fetch("https://api.iconify.design/evil.json");');
        expect(run(join(root, 'build'), 'scripts/vendored-urls.txt', join(root, 'src')).status).toBe(1);
    });

    it('fails on a vendored string added to application code', () => {
        const root = scratch();
        write(root, 'build/vendor.js', 'x("https://api.iconify.design");');
        write(root, 'src/Thing.ts', 'const url = "https://api.iconify.design";');
        const result = run(join(root, 'build'), 'scripts/vendored-urls.txt', join(root, 'src'));
        expect(result.status).toBe(1);
        expect(result.stderr).toContain('Thing.ts');
    });

    it('fails when there is no build to check', () => {
        const root = scratch();
        expect(run(join(root, 'missing'), 'scripts/vendored-urls.txt', join(root, 'src')).status).toBe(1);
    });

    it('lets application code hold the XML namespace identifiers', () => {
        const root = scratch();
        write(root, 'build/vendor.js', '');
        write(root, 'src/Icon.vue', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        expect(run(join(root, 'build'), 'scripts/vendored-urls.txt', join(root, 'src')).status).toBe(0);
    });
});
