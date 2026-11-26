import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { pinProblems } from '../../../proof/wp-e0/check-pins.mjs';

const pkg = JSON.parse(readFileSync('proof/wp-e0/package.json', 'utf8'));
const lock = JSON.parse(readFileSync('proof/wp-e0/package-lock.json', 'utf8'));
const report = readFileSync('specs/001-omc-studio-v1/wp-e0-report.md', 'utf8');

describe('WP-E0 P1', () => {
    it('pins every proof dependency exactly and matches the lockfile', () => {
        expect(pinProblems(pkg, lock)).toEqual([]);
    });

    it('resolves the drawing library to one exact version', () => {
        const versions = Object.entries(lock.packages as Record<string, { version?: string }>)
            .filter(([path]) => path.includes('node_modules/@open-pencil/'))
            .map(([, entry]) => entry.version);
        expect(versions.length).toBeGreaterThan(0);
        expect([...new Set(versions)]).toEqual(['0.16.0']);
    });

    it('records a P1 verdict in the report', () => {
        expect(report).toMatch(/^\| P1 \| .+ \| (met|partly met|not met) \|/m);
    });
});
