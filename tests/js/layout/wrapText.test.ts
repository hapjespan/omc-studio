import { describe, expect, it } from 'vitest';
import { wrapText } from '../../../resources/js/layout/wrapText';
import { fakeMeasurer } from './fakeMeasurer';
import type { FontSpec, TextMeasurer } from '../../../resources/js/layout/types';

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

    it('counts the letter spacing of the font in the measured width', () => {
        const spaced: FontSpec = { ...font, tracking: 0.2 };
        expect(wrapText('x'.repeat(20), 100, font, fakeMeasurer)).toHaveLength(1);
        expect(wrapText('x'.repeat(20), 100, spaced, fakeMeasurer).length).toBeGreaterThan(1);
    });

    it('gives deep-equal lines on two calls', () => {
        const text = 'Een lange naam met woorden en een extraordinarilylongwordthatbreaks';
        expect(wrapText(text, 120, font, fakeMeasurer)).toEqual(wrapText(text, 120, font, fakeMeasurer));
    });

    it('returns no lines for empty text', () => {
        expect(wrapText('  ', 100, font, fakeMeasurer)).toEqual([]);
    });

    it('keeps a surrogate pair whole when it breaks inside a word', () => {
        const text = `${'x'.repeat(19)}\u{1F600}${'x'.repeat(19)}`;
        expect(wrapText(text, 100, font, fakeMeasurer).join('')).toBe(text);
    });
});

const characterWise = (text: string, maxWidth: number, spec: FontSpec, measurer: TextMeasurer = fakeMeasurer): string[] => {
    const width = (value: string): number => measurer.width(value, spec) + (spec.tracking ?? 0) * spec.size * value.length;
    const lines: string[] = [];
    let current = '';
    const flush = (): void => {
        if (current !== '') lines.push(current);
        current = '';
    };

    for (const word of text.split(/\s+/).filter(Boolean)) {
        const candidate = current === '' ? word : `${current} ${word}`;
        if (width(candidate) <= maxWidth) {
            current = candidate;
            continue;
        }
        flush();
        if (width(word) <= maxWidth) {
            current = word;
            continue;
        }
        for (const char of word) {
            if (current !== '' && width(current + char) > maxWidth) flush();
            current += char;
        }
    }
    flush();

    return lines;
};

describe('wrapText matches the character by character reference', () => {
    const words = ['aa', 'Onderwijs', 'x'.repeat(37), 'Element', '0199f2a3-1d2c-7000-8000-000000000042', 'b', 'korte', 'y'.repeat(8)];
    const samples: string[] = [];
    let seed = 7;
    const next = (): number => (seed = (seed * 1103515245 + 12345) % 2147483648) / 2147483648;
    for (let index = 0; index < 400; index++) {
        samples.push(Array.from({ length: 1 + Math.floor(next() * 5) }, () => words[Math.floor(next() * words.length)]).join(' '));
    }

    const perGlyph: TextMeasurer = {
        width: (text: string, spec: FontSpec) => [...text].reduce((sum, char) => sum + spec.size * (0.3 + ((char.codePointAt(0) ?? 0) % 7) * 0.06), 0),
    };

    it('gives the same lines for every sample and width', () => {
        for (const text of samples) {
            for (const maxWidth of [12, 31, 60, 100, 237]) {
                expect(wrapText(text, maxWidth, font, fakeMeasurer)).toEqual(characterWise(text, maxWidth, font));
            }
        }
    });

    it('gives the same lines when every glyph has its own width', () => {
        for (const text of samples) {
            for (const maxWidth of [12, 31, 60, 100, 237]) {
                expect(wrapText(text, maxWidth, font, perGlyph)).toEqual(characterWise(text, maxWidth, font, perGlyph));
            }
        }
    });

    it('measures far fewer strings than the reference for a long unbreakable word', () => {
        const name = 'Element 0199f2a3-1d2c-7000-8000-000000000042';
        const counting = (): TextMeasurer & { calls: number } => {
            const spy = { calls: 0, width: (value: string, spec: FontSpec) => (spy.calls += 1, fakeMeasurer.width(value, spec)) };

            return spy;
        };
        const now = counting();
        const before = counting();
        wrapText(name, 130, font, now);
        characterWise(name, 130, font, before);

        expect(now.calls).toBeLessThan(before.calls / 3);
    });
});
