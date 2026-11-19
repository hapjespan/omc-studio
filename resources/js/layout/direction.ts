import { wrapText } from './wrapText';
import type { DirectionColumn, DirectionColumnKey, FontSpec, LayoutStatement, TextMeasurer } from './types';

export const DIRECTION_HEIGHT = 180;
export const DIRECTION_PADDING = 16;
export const DIRECTION_BODY_FONT: FontSpec = { family: 'Inter', size: 13, weight: 400, italic: false };
export const DIRECTION_LABEL_FONT: FontSpec = { family: 'Inter', size: 11, weight: 600, italic: false };
export const DIRECTION_BODY_LINE = 18;
const LABEL_LINE = 14;
const LABEL_GAP = 8;
const ELLIPSIS = '…';

const columnWidths: [DirectionColumnKey, number][] = [
    ['logo', 240],
    ['mission', 420],
    ['vision', 420],
    ['goals', 600],
];

export const directionMaxLines = (): number =>
    Math.floor((DIRECTION_HEIGHT - 2 * DIRECTION_PADDING - LABEL_LINE - LABEL_GAP) / DIRECTION_BODY_LINE);

const cut = (lines: string[], width: number, measurer: TextMeasurer): string[] => {
    const kept = lines.slice(0, directionMaxLines());
    let last = kept[kept.length - 1];
    while (last !== '' && measurer.width(last + ELLIPSIS, DIRECTION_BODY_FONT) > width) last = last.slice(0, -1);
    kept[kept.length - 1] = last.trimEnd() + ELLIPSIS;
    return kept;
};

export function layoutDirection(
    statements: LayoutStatement[],
    labels: { mission: string; vision: string; goals: string },
    measurer: TextMeasurer,
): DirectionColumn[] {
    const ordered = [...statements].sort((a, b) => a.position - b.position);
    const textOf = (key: DirectionColumnKey): string[] => {
        if (key === 'logo') return [];
        const kind = key === 'goals' ? 'goal' : key;
        return ordered.filter((statement) => statement.kind === kind).map((statement, index) => (key === 'goals' ? `${index + 1}. ${statement.title ? `${statement.title} ` : ''}${statement.body}` : statement.title ? `${statement.title}: ${statement.body}` : statement.body));
    };
    const goalTitles = ordered.filter((statement) => statement.kind === 'goal').map((statement) => statement.title);

    let x = 0;
    return columnWidths.map(([key, width]) => {
        const textWidth = width - 2 * DIRECTION_PADDING;
        const all = textOf(key).flatMap((text) => wrapText(text, textWidth, DIRECTION_BODY_FONT, measurer));
        const truncated = all.length > directionMaxLines();
        const column: DirectionColumn = {
            key,
            x,
            width,
            label: key === 'logo' ? null : labels[key],
            lines: truncated ? cut(all, textWidth, measurer) : all,
            truncated,
            boldLengths: key === 'goals' ? goalTitles.map((title, index) => (title ? `${index + 1}. ${title}`.length : 0)) : [],
        };
        x += width;
        return column;
    });
}
