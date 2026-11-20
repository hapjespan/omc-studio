import { describe, expect, it } from 'vitest';
import { formatPercentage, lineAmounts, parsePercentage, remaining, restToLine, splitEvenly, splitOverDomainActivities, type TargetNode } from '../../../resources/js/allocation/helpers';

const activity = (id: number): TargetNode => ({ id, kind: 'activity' });

describe('allocation helpers', () => {
    it('computes the remaining basis points', () => {
        expect(remaining([5000, 3000])).toBe(2000);
        expect(remaining([3333, 3333, 3334])).toBe(0);
        expect(remaining([5000, 5001])).toBe(-1);
    });

    it('puts the rest on one line', () => {
        expect(restToLine([5000, 3000], 1)).toEqual([5000, 5000]);
    });

    it('splits evenly with the extra basis point on the first lines', () => {
        expect(splitEvenly(3)).toEqual([3334, 3333, 3333]);
        expect(splitEvenly(4)).toEqual([2500, 2500, 2500, 2500]);
        expect(splitEvenly(0)).toEqual([]);
    });

    it('splits over the activities of a domain including those in clusters', () => {
        const domain: TargetNode = {
            id: 1,
            kind: 'domain',
            children: [activity(2), activity(3), { id: 4, kind: 'cluster', children: [activity(5), activity(6), activity(7)] }, activity(8)],
        };
        const split = splitOverDomainActivities(domain);
        expect(split.map((line) => line.basisPoints)).toEqual([1667, 1667, 1667, 1667, 1666, 1666]);
        expect(split.map((line) => line.id)).toEqual([2, 3, 5, 6, 7, 8]);
        expect(splitOverDomainActivities({ id: 9, kind: 'domain', children: [] })).toEqual([]);
    });

    it('mirrors the server split of an amount over lines', () => {
        expect(lineAmounts(23000, [3333, 3333, 3334])).toEqual([7666, 7666, 7668]);
        expect(lineAmounts(10001, [5000, 5000])).toEqual([5001, 5000]);
    });

    it('formats and parses percentages with two decimals', () => {
        expect(formatPercentage(3334)).toBe('33,34%');
        expect(formatPercentage(0)).toBe('0,00%');
        expect(formatPercentage(-1)).toBe('-0,01%');
        expect(parsePercentage('33,3')).toBe(3330);
        expect(parsePercentage('100')).toBe(10000);
        expect(parsePercentage('0,01%')).toBe(1);
        expect(parsePercentage('abc')).toBeNull();
        expect(parsePercentage('')).toBeNull();
    });
});
