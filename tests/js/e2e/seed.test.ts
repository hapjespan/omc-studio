import { describe, expect, it } from 'vitest';
import { marked } from '../../e2e/support/seed';

describe('marked', () => {
    it('reads the value behind the marker', () => {
        expect(marked('ID=12\n', 'ID')).toBe('12');
        expect(marked('OUT={"canvas":3}', 'OUT')).toBe('{"canvas":3}');
    });

    it('throws when the seed printed an error instead of the marker', () => {
        expect(() => marked('UNIQUE constraint failed: organisations.slug', 'ID')).toThrow('ID=');
    });
});
