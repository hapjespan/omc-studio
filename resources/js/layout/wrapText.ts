import type { FontSpec, TextMeasurer } from './types';

export function wrapText(text: string, maxWidth: number, font: FontSpec, measurer: TextMeasurer): string[] {
    const width = (value: string): number => measurer.width(value, font) + (font.tracking ?? 0) * font.size * value.length;
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
}
