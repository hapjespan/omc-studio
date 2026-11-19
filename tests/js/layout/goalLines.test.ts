import { describe, expect, it } from 'vitest';
import { GOAL_INDENT, goalLines } from '../../../resources/js/layout/direction';
import type { LayoutStatement, TextMeasurer } from '../../../resources/js/layout/types';
import { fakeMeasurer } from './fakeMeasurer';

const goal = (title: string | null, body: string, position = 1): LayoutStatement => ({ kind: 'goal', title, body, position });
const width = GOAL_INDENT + 6.5 * 12;

describe('goalLines', () => {
    it('numbers each goal and bolds only the title, per wrapped line', () => {
        expect(goalLines([goal('Groei', 'aaaaaaaaaaa 2. bbb'), goal(null, 'Rust', 2)], width, fakeMeasurer)).toEqual({
            lines: ['1. Groei', 'aaaaaaaaaaa', '2. bbb', '2. Rust'],
            marks: [{ number: 3, bold: 5 }, { number: 0, bold: 0 }, { number: 0, bold: 0 }, { number: 3, bold: 0 }],
        });
    });

    it('counts a title with collapsed whitespace by its collapsed length', () => {
        expect(goalLines([goal('A  B', 'c')], width, fakeMeasurer)).toEqual({ lines: ['1. A B c'], marks: [{ number: 3, bold: 3 }] });
    });

    it('keeps the bold length right across a mid-word break', () => {
        expect(goalLines([goal('Titelxxxxxxxxxxxxxx', 'b')], width, fakeMeasurer).marks).toEqual([{ number: 3, bold: 12 }, { number: 0, bold: 7 }]);
    });

    it('measures the wrapping at the bold weight', () => {
        const weighted: TextMeasurer = { width: (text, font) => text.length * font.size * (font.weight >= 600 ? 1 : 0.5) };
        expect(goalLines([goal(null, 'aaaa bbbb')], GOAL_INDENT + 13 * 5, weighted).lines).toEqual(['1. aaaa', 'bbbb']);
    });
});
