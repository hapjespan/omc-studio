import { cpus } from 'node:os';
import { describe, expect, it } from 'vitest';
import canvas from '../../fixtures/canvas-500.json';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import type { CanvasLayoutInput } from '../../../resources/js/layout/types';

describe('performance', () => {
    it('computes 500 Elements in under 50 ms', () => {
        const input = canvas as CanvasLayoutInput;
        computeCanvasLayout(input, fakeMeasurer);
        const runs = Array.from({ length: 5 }, () => {
            const start = performance.now();
            computeCanvasLayout(input, fakeMeasurer);
            return performance.now() - start;
        }).sort((a, b) => a - b);
        const median = runs[2];
        console.info(`layout of ${input.elements.length} Elements: median ${median.toFixed(1)} ms on ${cpus()[0]?.model ?? 'unknown cpu'} (${cpus().length} cores)`);
        expect(median).toBeLessThan(50);
    });
});
