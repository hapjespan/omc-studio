import { describe, expect, it } from 'vitest';
import { basisPointsOf, formatBasisPoints } from '../../../resources/js/format';

describe('formatBasisPoints', () => {
    it.each([[1235, '12,4%'], [1234, '12,3%'], [10000, '100,0%'], [0, '0,0%'], [4, '0,0%'], [5, '0,1%']])('%i gives %s', (input, out) => expect(formatBasisPoints(input)).toBe(out));
});

describe('basisPointsOf', () => {
    it('rounds half up to a basis point and gives null for an empty whole', () => {
        expect(basisPointsOf(1, 3)).toBe(3333);
        expect(basisPointsOf(2, 3)).toBe(6667);
        expect(basisPointsOf(5, 0)).toBeNull();
    });
});

describe('basisPointsOf for negatives', () => {
    it('truncates toward zero like the server', () => {
        expect(basisPointsOf(-1, 3)).toBe(-3333);
        expect(basisPointsOf(-2, 3)).toBe(-6666);
    });
});
