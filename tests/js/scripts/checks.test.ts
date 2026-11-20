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

describe('check-assets over the final build', () => {
    const chunks = (extra: Record<string, string>) => tree({ 'assets/app-AbC.js': 'export {};', 'assets/edit-engine-XyZ.js': 'export {};', 'assets/html-to-image-123.js': 'export {};', ...extra });

    it('passes a build whose Tekenen bundle and html-to-image chunk hold no outside URL', () => {
        expect(run('check-assets.mjs', [chunks({})]).status).toBe(0);
    });

    it('fails an injected outside URL in the Tekenen bundle', () => {
        const result = run('check-assets.mjs', [chunks({ 'assets/edit-engine-XyZ.js': 'fetch("https://cdn.example.org/x");' })]);
        expect(result.status).toBe(1);
        expect(result.stderr).toContain('edit-engine-XyZ.js');
    });

    it('fails an injected outside URL in the html-to-image chunk', () => {
        const result = run('check-assets.mjs', [chunks({ 'assets/html-to-image-123.js': 'img.src = "https://cdn.example.org/y.png";' })]);
        expect(result.status).toBe(1);
        expect(result.stderr).toContain('html-to-image-123.js');
    });
});

describe('run-checks', () => {
    const script = (name: string, code: number) => {
        const root = tree({ [name]: `process.exit(${code});` });
        return join(root, name);
    };
    const runner = (list: [string, string][], allowed = '') =>
        spawnSync('node', ['scripts/run-checks.mjs'], { encoding: 'utf8', env: { ...process.env, RUN_CHECKS_LIST: JSON.stringify(list), ALLOWED_SKIPS: allowed } });

    it('passes when every check passes', () => {
        const result = runner([['one', script('a.mjs', 0)], ['two', script('b.mjs', 0)]]);
        expect(result.status).toBe(0);
        expect(result.stdout).toContain('checks: 2 passed, 0 skipped, 0 skipped without permission, 0 failed');
    });

    it('fails when a check fails', () => {
        const result = runner([['one', script('a.mjs', 0)], ['two', script('b.mjs', 1)]]);
        expect(result.status).toBe(1);
        expect(result.stdout).toContain('1 failed');
    });

    it('fails on a skip that nobody allowed, naming it', () => {
        const result = runner([['forbidden names', script('a.mjs', 3)]]);
        expect(result.status).toBe(1);
        expect(result.stdout).toContain('1 skipped without permission (forbidden names)');
    });

    it('reports an allowed skip as skipped and not as passed', () => {
        const result = runner([['forbidden names', script('a.mjs', 3)]], 'forbidden names');
        expect(result.status).toBe(0);
        expect(result.stdout).toContain('0 passed, 1 skipped (forbidden names)');
    });

    it('does not let a failure hide behind an allowed skip', () => {
        const result = runner([['forbidden names', script('a.mjs', 3)], ['two', script('b.mjs', 2)]], 'forbidden names');
        expect(result.status).toBe(1);
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

    it('prints a visible skip with an exit code of its own when the list is empty', () => {
        const root = tree({ 'a.txt': 'hello' });
        const result = run('check-forbidden-names.mjs', [root, list('\n')]);
        expect(result.stdout).toContain('overgeslagen');
        expect(result.status).toBe(3);
    });

    it('prints a visible skip that does not count as passed when the list is missing', () => {
        const root = tree({ 'a.txt': 'hello' });
        const result = run('check-forbidden-names.mjs', [root, join(root, 'absent.txt')]);
        expect(result.stdout).toContain('overgeslagen: lijst verboden namen ontbreekt');
        expect(result.stdout).not.toContain('passed');
        expect(result.status).toBe(3);
    });

    it('finds a name in a path and in a file, whatever the case', () => {
        const root = tree({ 'Docs/Acme-notes.md': 'nothing', 'src/b.ts': 'const x = "ACME";' });
        const result = run('check-forbidden-names.mjs', [root, list('Acme\n')]);
        expect(result.status).toBe(1);
        expect(result.stderr).toContain('Acme-notes.md');
        expect(result.stderr).toContain('b.ts');
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
