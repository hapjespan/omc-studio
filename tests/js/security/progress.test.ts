import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';

const app = readFileSync('resources/js/app.ts', 'utf8');
const stylesheet = readFileSync('resources/css/app.css', 'utf8');

describe('inertia progress indicator', () => {
    it('is configured without its own style element', () => {
        expect(app).toMatch(/progress:\s*\{[^}]*includeCSS:\s*false/);
    });

    it('is styled from the stylesheet', () => {
        expect(stylesheet).toContain('#nprogress .bar');
    });
});
