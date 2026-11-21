import { spawnSync } from 'node:child_process';
import { chmodSync, copyFileSync, existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';

const pins = JSON.parse(readFileSync('package.json', 'utf8')) as { dependencies: Record<string, string> };
const lock = JSON.parse(readFileSync('package-lock.json', 'utf8')) as { packages: Record<string, { version: string }> };
const names = ['@open-pencil/core', '@open-pencil/vue'];
const sandboxes: string[] = [];

interface Sandbox {
    root: string;
    run: (...args: string[]) => ReturnType<typeof spawnSync>;
    calls: () => string;
    read: (file: string) => string;
}

function sandbox(npxStatus: number, withSuite = true): Sandbox {
    mkdirSync('.scratch', { recursive: true });
    const root = mkdtempSync(join('.scratch', 'upgrade-'));
    sandboxes.push(root);
    for (const dir of ['scripts', 'bin', 'tests/js/edit-engine', 'tests/js/layout']) mkdirSync(join(root, dir), { recursive: true });
    copyFileSync('scripts/upgrade-open-pencil.sh', join(root, 'scripts/upgrade-open-pencil.sh'));
    writeFileSync(join(root, 'package.json'), '{"dependencies":{"@open-pencil/core":"0.16.0","@open-pencil/vue":"0.16.0"}}');
    writeFileSync(join(root, 'package-lock.json'), '{"lock":"original"}');
    writeFileSync(join(root, 'tests/js/layout/pictureCompare.test.ts'), '');
    if (withSuite) writeFileSync(join(root, 'tests/js/edit-engine/roundtrip.test.ts'), '');
    const stub = (name: string, body: string) => {
        writeFileSync(join(root, 'bin', name), `#!/bin/sh\necho "${name} $*" >> "${resolve(root)}/calls.log"\n${body}\n`);
        chmodSync(join(root, 'bin', name), 0o755);
    };
    stub('npm', `case "$1" in install) echo '{"dependencies":{"changed":"1"}}' > package.json; echo '{"lock":"changed"}' > package-lock.json;; esac`);
    stub('npx', `exit ${npxStatus}`);
    stub('node', 'echo 0.99.0');

    return {
        root,
        run: (...args) => spawnSync('sh', [join(root, 'scripts/upgrade-open-pencil.sh'), ...args], { encoding: 'utf8', env: { ...process.env, PATH: `${join(process.cwd(), root, 'bin')}:${process.env.PATH}` }, cwd: process.cwd() }),
        calls: () => (existsSync(join(root, 'calls.log')) ? readFileSync(join(root, 'calls.log'), 'utf8') : ''),
        read: (file) => readFileSync(join(root, file), 'utf8'),
    };
}

afterEach(() => sandboxes.splice(0).forEach((root) => rmSync(root, { recursive: true, force: true })));

describe('OpenPencil pins', () => {
    it.each(names)('pins %s exactly in package.json and the lockfile', (name) => {
        expect(pins.dependencies[name]).toMatch(/^\d+\.\d+\.\d+$/);
        expect(lock.packages[`node_modules/${name}`].version).toBe(pins.dependencies[name]);
    });

    it('keeps both packages on the same version', () => {
        expect(pins.dependencies[names[0]]).toBe(pins.dependencies[names[1]]);
    });

    it('is documented', () => {
        expect(existsSync('docs/process/upgrade-open-pencil.md')).toBe(true);
    });
});

describe('upgrade script', () => {
    it('restores package.json and the lockfile and exits non-zero when the suite is red', () => {
        const box = sandbox(1);
        const result = box.run('0.17.0');

        expect(result.status).not.toBe(0);
        expect(box.read('package.json')).toContain('"0.16.0"');
        expect(box.read('package-lock.json')).toBe('{"lock":"original"}');
        expect(box.calls()).toContain('npm install --save-exact --no-audit --no-fund @open-pencil/core@0.17.0 @open-pencil/vue@0.17.0');
        expect(box.calls()).toContain('npm ci');
    });

    it('keeps the new pins and exits zero when the suite is green', () => {
        const box = sandbox(0);
        const result = box.run('0.17.0');

        expect(result.status).toBe(0);
        expect(box.read('package-lock.json')).toContain('changed');
        expect(box.calls()).not.toContain('npm ci');
        expect(box.calls()).toContain('npx vitest run tests/js/edit-engine tests/js/layout/pictureCompare.test.ts');
    });

    it('checks the current pins without installing or restoring anything', () => {
        const box = sandbox(1);
        const result = box.run('--check');

        expect(result.status).not.toBe(0);
        expect(box.calls()).not.toContain('npm');
        expect(box.read('package-lock.json')).toBe('{"lock":"original"}');
    });

    it.each([[[]], [['^0.17.0']], [['latest']], [['0.17']], [['0.17.0\nlatest']], [['1.2.3.4']]])('refuses %j and touches nothing', (args) => {
        const box = sandbox(0);
        const result = box.run(...args);

        expect(result.status).not.toBe(0);
        expect(box.calls()).toBe('');
        expect(box.read('package.json')).toContain('"0.16.0"');
    });

    it('refuses a directory without adapter suites', () => {
        const box = sandbox(0, false);
        const result = box.run('0.17.0');

        expect(result.status).not.toBe(0);
        expect(result.stderr).toContain('holds no adapter suite');
        expect(box.calls()).toBe('');
    });
});
