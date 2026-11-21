import { cpus } from 'node:os';
import { describe, expect, it } from 'vitest';
import canvas from '../../fixtures/canvas-500.json';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import type { CanvasLayoutInput, ElementLayout } from '../../../resources/js/layout/types';

const input = canvas as CanvasLayoutInput;

const layouts: Record<string, ElementLayout> = {};
let domains = 0;
const siblings = new Map<string, number>();
for (const element of input.elements) {
    if (element.kind === 'domain') {
        layouts[element.id] = { mode: 'manual', x: 12 + (domains % 4) * 200, y: 40 + Math.floor(domains / 4) * 90, width: 190, height: 80, z: domains % 3 };
        domains++;
        continue;
    }
    const index = siblings.get(element.parent ?? '') ?? 0;
    siblings.set(element.parent ?? '', index + 1);
    layouts[element.id] = { mode: 'manual', x: 8, y: 8 + index * 30, width: 150, height: 24, z: 0 };
}

describe('performance with every Element in Manual layout', () => {
    it('computes 500 Elements, all Manual, in under 50 ms (median of 21 runs)', () => {
        const withLayouts = { ...input, layouts };
        computeCanvasLayout(withLayouts, fakeMeasurer);
        const runs = Array.from({ length: 21 }, () => {
            const start = performance.now();
            computeCanvasLayout(withLayouts, fakeMeasurer);
            return performance.now() - start;
        }).sort((a, b) => a - b);
        const median = runs[10];
        console.info(`manual layout of ${input.elements.length} Elements, all Manual: median ${median.toFixed(1)} ms on ${cpus()[0]?.model ?? 'unknown cpu'} (${cpus().length} cores)`);
        expect(Object.keys(layouts)).toHaveLength(input.elements.length);
        expect(median).toBeLessThan(50);
    });
});
