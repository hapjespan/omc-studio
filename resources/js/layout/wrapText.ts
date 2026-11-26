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
        const joined = current === '' ? word : `${current} ${word}`;
        if (width(joined) <= maxWidth) {
            current = joined;
            continue;
        }
        const alone = current === '';
        flush();
        if (!alone && width(word) <= maxWidth) {
            current = word;
            continue;
        }
        const chars = [...word];
        for (let start = 0; start < chars.length; ) {
            const take = (count: number): string => chars.slice(start, start + count).join('');
            let high = chars.length - start;
            if (start > 0 && width(take(high)) <= maxWidth) {
                current = take(high);
                break;
            }
            let low = 1;
            while (high - low > 1) {
                const middle = (low + high) >> 1;
                if (width(take(middle)) <= maxWidth) low = middle;
                else high = middle;
            }
            if (start + low === chars.length) {
                current = take(low);
                break;
            }
            lines.push(take(low));
            start += low;
        }
    }
    flush();

    return lines;
}
