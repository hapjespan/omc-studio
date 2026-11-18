import { describe, expect, it } from 'vitest';
import fixture from '../../fixtures/formats.json';
import * as format from '../../../resources/js/format';

type Case = { in: (string | number)[]; out: string };
const functions = format as unknown as Record<string, (...args: (string | number)[]) => string>;

describe.each(Object.entries(fixture as Record<string, Case[]>))('%s', (name, cases) => {
    it.each(cases)('$in gives $out', ({ in: args, out }) => {
        expect(functions[name](...args)).toBe(out);
    });
});
