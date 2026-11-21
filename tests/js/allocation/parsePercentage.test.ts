import { existsSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import fixture from '../../fixtures/amounts.json';
import { parsePercentage } from '../../../resources/js/allocation/helpers';

describe('parsePercentage against the shared percentage fixture', () => {
    it.each(fixture.percentage)('$in gives the server value, or the editor value where the editor is more lenient', (row) => {
        const expected = 'editor' in row ? (row as { editor: number }).editor : row.bp;

        expect(parsePercentage(row.in)).toBe(expected);
    });

    it('accepts the lenient editor forms', () => {
        expect(parsePercentage('0,01%')).toBe(1);
        expect(parsePercentage(' 12.5 % ')).toBe(1250);
    });

    it('has no second percentage parser', () => {
        expect(existsSync('resources/js/format/parseAmount.ts')).toBe(false);
    });
});
