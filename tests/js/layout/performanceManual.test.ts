import { cpus } from 'node:os';
import { describe, expect, it } from 'vitest';
import canvas from '../../fixtures/canvas-500.json';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import type { CanvasLayoutInput, ElementLayout } from '../../../resources/js/layout/types';

const input = canvas as CanvasLayoutInput;

const layouts: Record<string, ElementLayout> = Object.fromEntries(
    input.elements
        .filter((element) => element.kind === 'domain')
        .map((element, index) => [element.id, { mode: 'manual', x: 12 + (index % 4) * 200, y: 40 + Math.floor(index / 4) * 90, width: 190, height: 80, z: index % 3 } satisfies ElementLayout]),
);

describe('performance with a Manual layout', () => {
    it('computes 500 Elements with Manual Domains in under 50 ms', () => {
        const withLayouts = { ...input, layouts };
        computeCanvasLayout(withLayouts, fakeMeasurer);
        const runs = Array.from({ length: 5 }, () => {
            const start = performance.now();
            computeCanvasLayout(withLayouts, fakeMeasurer);
            return performance.now() - start;
        }).sort((a, b) => a - b);
        const median = runs[2];
        console.info(`manual layout of ${input.elements.length} Elements (${Object.keys(layouts).length} manual Domains): median ${median.toFixed(1)} ms on ${cpus()[0]?.model ?? 'unknown cpu'} (${cpus().length} cores)`);
        expect(Object.keys(layouts).length).toBeGreaterThan(10);
        expect(median).toBeLessThan(50);
    });
});
