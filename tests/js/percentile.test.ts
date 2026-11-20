import { describe, expect, it } from 'vitest';
import { percentile } from '../e2e/support/percentile';

describe('percentile', () => {
    it('takes the nearest rank of the sorted samples', () => {
        const samples = Array.from({ length: 20 }, (_, index) => (index + 1) * 100);

        expect(percentile(samples, 95)).toBe(1900);
        expect(percentile(samples, 50)).toBe(1000);
        expect(percentile(samples, 100)).toBe(2000);
    });

    it('lets one outlier in twenty samples pass the 95th percentile and refuses two', () => {
        const base = Array.from({ length: 20 }, () => 800);

        expect(percentile([...base.slice(1), 5000], 95)).toBe(800);
        expect(percentile([...base.slice(2), 5000, 5000], 95)).toBe(5000);
    });

    it('does not depend on the order of the samples', () => {
        expect(percentile([300, 100, 200], 50)).toBe(200);
    });

    it('refuses an empty sample', () => {
        expect(() => percentile([], 95)).toThrow();
    });
});
