import { spawnSync } from 'node:child_process';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

const advisory = (id: string) => ({ name: 'image-size', severity: 'high', url: `https://github.com/advisories/${id}` });

const accepted = {
    '@open-pencil/core': { severity: 'high', via: ['pptxgenjs'] },
    '@open-pencil/vue': { severity: 'high', via: ['@open-pencil/core'] },
    pptxgenjs: { severity: 'high', via: ['image-size'] },
    'image-size': { severity: 'high', via: [advisory('GHSA-5p2g-fcmc-qvqq'), advisory('GHSA-w3rx-r6r6-pgpr')] },
};

function run(vulnerabilities: object) {
    const path = join(mkdtempSync(join(tmpdir(), 'audit-')), 'audit.json');
    writeFileSync(path, JSON.stringify({ vulnerabilities }));
    return spawnSync('node', ['scripts/check-audit.mjs', path], { encoding: 'utf8' });
}

describe('check-audit', () => {
    it('passes on the accepted four', () => {
        expect(run(accepted).status).toBe(0);
    });

    it('passes on no findings at all', () => {
        expect(run({}).status).toBe(0);
    });

    it('ignores findings below high', () => {
        expect(run({ left: { severity: 'moderate', via: [] } }).status).toBe(0);
    });

    it('fails on another high finding and names it', () => {
        const result = run({ ...accepted, lodash: { severity: 'high', via: [] } });
        expect(result.status).toBe(1);
        expect(result.stderr).toContain('lodash');
    });

    it('fails on a critical finding', () => {
        expect(run({ ...accepted, evil: { severity: 'critical', via: [] } }).status).toBe(1);
    });

    it('fails on a new advisory of an accepted package and names it', () => {
        const result = run({ ...accepted, 'image-size': { severity: 'high', via: [advisory('GHSA-5p2g-fcmc-qvqq'), advisory('GHSA-aaaa-bbbb-cccc')] } });
        expect(result.status).toBe(1);
        expect(result.stderr).toContain('GHSA-aaaa-bbbb-cccc');
    });

    it('fails on a report that holds an error instead of findings', () => {
        const path = join(mkdtempSync(join(tmpdir(), 'audit-')), 'audit.json');
        writeFileSync(path, JSON.stringify({ error: { code: 'ENOTFOUND' } }));
        expect(spawnSync('node', ['scripts/check-audit.mjs', path], { encoding: 'utf8' }).status).toBe(1);
    });

    it('fails when the report is unreadable', () => {
        expect(spawnSync('node', ['scripts/check-audit.mjs', 'missing.json'], { encoding: 'utf8' }).status).toBe(1);
    });
});
