import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { existsSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const built = existsSync('proof/wp-e0/dist-page/page.html');

describe.skipIf(!built)('P6 asset check (FR-098) on the proof bundle', () => {
    it('finding: the application asset check fails on the as-shipped bundle', () => {
        const run = spawnSync('node', ['scripts/check-assets.mjs', 'proof/wp-e0/dist-page'], { encoding: 'utf8' });
        expect(run.status).toBe(1);
        expect(run.stderr).toContain('outside URL in');
    });

    it('finding: the outside URL strings fall in four categories, none of them a request made at runtime', () => {
        const run = spawnSync('node', ['proof/wp-e0/asset-report.mjs'], { encoding: 'utf8' });
        expect(run.status).toBe(1);
        const categories = Object.keys(JSON.parse(readFileSync('specs/001-omc-studio-v1/wp-e0-evidence/p6-assets.json', 'utf8')).grouped).sort();
        expect(categories).toEqual(['AI provider key pages', 'XML namespace identifiers', 'documentation and example links', 'provider endpoints that would be requested if enabled']);
    });
});
