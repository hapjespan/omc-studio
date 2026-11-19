import { describe, expect, it } from 'vitest';
import fixture from '../../fixtures/amounts.json';
import { parseAmount, parsePercentage } from '../../../resources/js/format/parseAmount';

describe('parseAmount', () => {
    it.each(fixture.amount)('$in gives $cents', ({ in: text, cents }) => {
        expect(parseAmount(text)).toBe(cents);
    });
});

describe('parsePercentage', () => {
    it.each(fixture.percentage)('$in gives $bp', ({ in: text, bp }) => {
        expect(parsePercentage(text)).toBe(bp);
    });
});
