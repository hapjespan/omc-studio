import { describe, expect, it } from 'vitest';
import fixture from '../../fixtures/largest-remainder.json';
import { largestRemainder } from '../../../resources/js/allocation/largestRemainder';

describe('largestRemainder', () => {
    it.each(fixture)('$name', ({ total, weights, expected }) => {
        expect(largestRemainder(total, weights)).toEqual(expected);
    });
});
