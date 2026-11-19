import type { FontSpec, TextMeasurer } from './types';

export function wrapText(text: string, maxWidth: number, font: FontSpec, measurer: TextMeasurer): string[] {
    const lines: string[] = [];
    let current = '';
    const flush = (): void => {
        if (current !== '') lines.push(current);
        current = '';
    };

    for (const word of text.split(/\s+/).filter(Boolean)) {
        const candidate = current === '' ? word : `${current} ${word}`;
        if (measurer.width(candidate, font) <= maxWidth) {
            current = candidate;
            continue;
        }
        flush();
        if (measurer.width(word, font) <= maxWidth) {
            current = word;
            continue;
        }
        for (const char of word) {
            if (current !== '' && measurer.width(current + char, font) > maxWidth) flush();
            current += char;
        }
    }
    flush();

    return lines;
}
