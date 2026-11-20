import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const pins = JSON.parse(readFileSync('package.json', 'utf8')) as { dependencies: Record<string, string> };
const lock = JSON.parse(readFileSync('package-lock.json', 'utf8')) as { packages: Record<string, { version: string }> };
const script = readFileSync('scripts/upgrade-open-pencil.sh', 'utf8');
const names = ['@open-pencil/core', '@open-pencil/vue'];
const run = (...args: string[]) => spawnSync('sh', ['scripts/upgrade-open-pencil.sh', ...args], { encoding: 'utf8' });

describe('OpenPencil pins', () => {
    it.each(names)('pins %s exactly in package.json and the lockfile', (name) => {
        expect(pins.dependencies[name]).toMatch(/^\d+\.\d+\.\d+$/);
        expect(lock.packages[`node_modules/${name}`].version).toBe(pins.dependencies[name]);
    });

    it('keeps both packages on the same version', () => {
        expect(pins.dependencies[names[0]]).toBe(pins.dependencies[names[1]]);
    });
});

describe('upgrade script', () => {
    it('exists and reads the adapter suites by directory with the picture test', () => {
        expect(existsSync('docs/process/upgrade-open-pencil.md')).toBe(true);
        expect(statSync('scripts/upgrade-open-pencil.sh').size).toBeGreaterThan(0);
        expect(script).toContain('SUITES="tests/js/edit-engine"');
        expect(script).toContain('tests/js/layout/pictureCompare.test.ts');
        expect(script).toContain('npx vitest run "$SUITES" "$PICTURE"');
    });

    it('restores package.json and the lockfile when the suite is red', () => {
        expect(script).toMatch(/restore\(\) \{[\s\S]*cp "\$backup"\/package.json "\$backup"\/package-lock.json \.[\s\S]*npm ci/);
        expect(script).toContain('[ -n "$version" ] && restore');
    });

    it.each([[[]], [['^0.17.0']], [['latest']], [['0.17']]])('refuses %j before touching anything', (args) => {
        const result = run(...args);

        expect(result.status).not.toBe(0);
        expect(result.stderr).toMatch(/usage|exact/);
    });
});
