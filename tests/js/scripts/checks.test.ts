import { spawnSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

function run(script: string, args: string[]) {
    return spawnSync('node', [`scripts/${script}`, ...args], { encoding: 'utf8' });
}

function tree(files: Record<string, string>) {
    const root = mkdtempSync(join(tmpdir(), 'checks-'));
    for (const [path, content] of Object.entries(files)) {
        mkdirSync(join(root, path, '..'), { recursive: true });
        writeFileSync(join(root, path), content);
    }
    return root;
}

function list(content: string) {
    const path = join(mkdtempSync(join(tmpdir(), 'names-')), 'names.txt');
    writeFileSync(path, content);
    return path;
}

describe('check-assets', () => {
    it('passes on assets without an outside URL', () => {
        const root = tree({ 'app.js': 'const a = "http://www.w3.org/2000/svg";' });
        expect(run('check-assets.mjs', [root]).status).toBe(0);
    });

    it('fails on a look-alike host', () => {
        const root = tree({ 'app.js': 'x("http://localhost.evil.example/a");' });
        expect(run('check-assets.mjs', [root]).status).toBe(1);
    });

    it('fails on an outside URL', () => {
        const root = tree({ 'app.js': 'fetch("https://cdn.example.org/x.js");' });
        expect(run('check-assets.mjs', [root]).status).toBe(1);
    });
});

describe('check-forbidden-names', () => {
    it('fails on a hit, ignoring case', () => {
        const root = tree({ 'a.txt': 'Hello ACME Corp' });
        expect(run('check-forbidden-names.mjs', [root, list('acme\n')]).status).toBe(1);
    });

    it('passes when no name occurs', () => {
        const root = tree({ 'a.txt': 'hello' });
        expect(run('check-forbidden-names.mjs', [root, list('acme\n')]).status).toBe(0);
    });

    it('prints a visible skip when the list is empty', () => {
        const root = tree({ 'a.txt': 'hello' });
        expect(run('check-forbidden-names.mjs', [root, list('\n')]).stdout).toContain('overgeslagen');
    });

    it('prints a visible skip when the list is missing', () => {
        const root = tree({ 'a.txt': 'hello' });
        const result = run('check-forbidden-names.mjs', [root, join(root, 'absent.txt')]);
        expect(result.stdout).toContain('overgeslagen: lijst verboden namen ontbreekt');
        expect(result.stdout).not.toContain('passed');
    });
});

describe('check-imports', () => {
    it('fails on an @open-pencil import outside the adapter', () => {
        const root = tree({ 'resources/js/pages/A.ts': 'import x from "@open-pencil/core";' });
        expect(run('check-imports.mjs', [root]).status).toBe(1);
    });

    it('passes inside the adapter', () => {
        const root = tree({ 'resources/js/edit-engine/a.ts': 'import x from "@open-pencil/core";' });
        expect(run('check-imports.mjs', [root]).status).toBe(0);
    });
});
