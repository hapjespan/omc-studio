import { describe, expect, it } from 'vitest';
import { pictureDifferences, type EngineBlock, type Measured } from '../../e2e/support/pictureCompare';

const engine: EngineBlock = { id: 'a', x: 10, y: 20, width: 100, drawnHeight: 40, textLines: ['Een', 'twee'] };
const measured = (extra: Partial<Measured> = {}): Measured => ({ id: 'a', x: 10, y: 20, width: 100, height: 40, lines: ['Een', 'twee'], widest: 50, ...extra });

describe('pictureDifferences', () => {
    it('accepts an equal picture and a difference of one pixel', () => {
        expect(pictureDifferences([engine], [measured()])).toEqual([]);
        expect(pictureDifferences([engine], [measured({ x: 11, height: 39.2 })])).toEqual([]);
    });

    it('names a place or size that differs by more than one pixel', () => {
        expect(pictureDifferences([engine], [measured({ y: 21.5 })])).toEqual(['a: y differs by 1.50']);
        expect(pictureDifferences([engine], [measured({ width: 98 })])).toEqual(['a: width differs by -2.00']);
    });

    it('names other text lines, a missing block and a line wider than its block', () => {
        expect(pictureDifferences([engine], [measured({ lines: ['Een twee'] })])).toEqual(['a: text lines differ']);
        expect(pictureDifferences([engine], [])).toEqual(['a: not drawn']);
        expect(pictureDifferences([engine], [measured({ widest: 102 })])).toEqual(['a: a line is wider than the block']);
    });
});
