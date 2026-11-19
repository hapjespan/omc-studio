import { describe, expect, it } from 'vitest';
import { wrapText } from '../../../resources/js/layout/wrapText';
import { fakeMeasurer } from './fakeMeasurer';
import type { FontSpec } from '../../../resources/js/layout/types';

const font: FontSpec = { family: 'Inter', size: 10, weight: 400, italic: false };

describe('wrapText', () => {
    it('keeps a short name on one line', () => {
        expect(wrapText('Finance', 100, font, fakeMeasurer)).toEqual(['Finance']);
    });

    it('wraps at word boundaries', () => {
        expect(wrapText('aaaa bbbb cccc dddd eeee', 100, font, fakeMeasurer)).toEqual(['aaaa bbbb cccc dddd', 'eeee']);
    });

    it('breaks a 40 character word over several lines in 100 pixels', () => {
        const lines = wrapText('x'.repeat(40), 100, font, fakeMeasurer);
        expect(lines).toEqual(['x'.repeat(20), 'x'.repeat(20)]);
        expect(lines.join('')).toBe('x'.repeat(40));
        expect(wrapText('x'.repeat(40), 60, font, fakeMeasurer).length).toBeGreaterThan(2);
    });

    it('starts a long word on a new line after short words', () => {
        expect(wrapText(`ab ${'y'.repeat(30)}`, 100, font, fakeMeasurer)).toEqual(['ab', 'y'.repeat(20), 'y'.repeat(10)]);
    });

    it('gives deep-equal lines on two calls', () => {
        const text = 'Een lange naam met woorden en een extraordinarilylongwordthatbreaks';
        expect(wrapText(text, 120, font, fakeMeasurer)).toEqual(wrapText(text, 120, font, fakeMeasurer));
    });

    it('returns no lines for empty text', () => {
        expect(wrapText('  ', 100, font, fakeMeasurer)).toEqual([]);
    });
});
