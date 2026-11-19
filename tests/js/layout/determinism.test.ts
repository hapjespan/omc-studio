import { describe, expect, it } from 'vitest';
import canvas from '../../fixtures/canvas-500.json';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';
import type { CanvasLayoutInput } from '../../../resources/js/layout/types';

const fixture = canvas as CanvasLayoutInput;

describe('determinism', () => {
    it('gives deep-equal output for the same input twice', () => {
        expect(computeCanvasLayout(fixture, fakeMeasurer)).toEqual(computeCanvasLayout(fixture, fakeMeasurer));
    });

    it('does not depend on the order of the input Elements', () => {
        const shuffled = { ...fixture, elements: [...fixture.elements].reverse() };
        expect(computeCanvasLayout(shuffled, fakeMeasurer)).toEqual(computeCanvasLayout(fixture, fakeMeasurer));
    });

    it('does not mutate its input', () => {
        const before = JSON.stringify(fixture);
        computeCanvasLayout(fixture, fakeMeasurer);
        expect(JSON.stringify(fixture)).toBe(before);
    });

    it('moves every block after a growth chain and still agrees with itself', () => {
        const chain = (name: string) =>
            withElements([
                element('d', 'primary_activities', 'domain', 'Domein', null, 1),
                element('k', 'primary_activities', 'cluster', 'Cluster', 'd', 1),
                element('a', 'primary_activities', 'activity', name, 'k', 1),
                element('n', 'primary_activities', 'domain', 'Volgende', null, 2),
            ]);
        const short = computeCanvasLayout(chain('Kort'), fakeMeasurer);
        const long = computeCanvasLayout(chain('woord '.repeat(60)), fakeMeasurer);
        expect(computeCanvasLayout(chain('woord '.repeat(60)), fakeMeasurer)).toEqual(long);
        const heightOf = (result: typeof short, id: string) => result.blocks.find((block) => block.id === id)!.drawnHeight;
        for (const id of ['a', 'k', 'd']) expect(heightOf(long, id)).toBeGreaterThan(heightOf(short, id));
    });

    it('draws every Element of the 500 Element fixture once, inside the width of the Artboard', () => {
        const result = computeCanvasLayout(fixture, fakeMeasurer);
        const ids = result.blocks.filter((block) => block.kind !== 'area').map((block) => block.id);
        expect(new Set(ids).size).toBe(ids.length);
        expect(ids.length).toBe(fixture.elements.length);
        expect(result.blocks.every((block) => block.x >= 0 && block.x + block.width <= 1680)).toBe(true);
    });
});
