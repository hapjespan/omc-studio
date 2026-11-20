import { cpus } from 'node:os';
import { describe, expect, it } from 'vitest';
import canvas from '../../fixtures/canvas-500.json';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { percentile } from '../../e2e/support/percentile';
import { fakeMeasurer } from './fakeMeasurer';
import type { CanvasLayoutInput, ElementLayout } from '../../../resources/js/layout/types';

const input = canvas as CanvasLayoutInput;

const manualFor = (every: number): Record<string, ElementLayout> =>
    Object.fromEntries(
        input.elements
            .map((element, index): [string, ElementLayout] | null =>
                index % every === 0 ? [element.id, { mode: 'manual', x: 8 + (index % 7) * 40, y: 10 + (index % 11) * 30, width: 220, height: 60, z: index % 5 }] : null,
            )
            .filter((entry): entry is [string, ElementLayout] => entry !== null),
    );

const p95 = (layouts: Record<string, ElementLayout>): number => {
    const run = () => computeCanvasLayout({ ...input, layouts }, fakeMeasurer);
    run();
    const samples = Array.from({ length: 20 }, () => {
        const start = performance.now();
        run();
        return performance.now() - start;
    });

    return percentile(samples, 95);
};

describe('performance with Manual layout', () => {
    it.each([
        ['every Element', 1],
        ['every third Element', 3],
    ])('computes 500 Elements with Manual layout on %s in under 50 ms at the 95th percentile', (_label, every) => {
        const value = p95(manualFor(every));
        console.info(`manual layout of ${input.elements.length} Elements (${_label}): p95 ${value.toFixed(1)} ms on ${cpus()[0]?.model ?? 'unknown cpu'} (${cpus().length} cores)`);
        expect(value).toBeLessThan(50);
    });
});
