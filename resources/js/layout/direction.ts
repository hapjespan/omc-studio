import limits from '../../data/layout-limits.json';
import { wrapText } from './wrapText';
import type { DirectionColumn, DirectionColumnKey, FontSpec, LayoutStatement, LineMark, TextMeasurer } from './types';

export const DIRECTION_HEIGHT = limits.directionHeight;
export const DIRECTION_PADDING = 16;
export const DIRECTION_BODY_FONT: FontSpec = { family: 'Inter', size: 13, weight: 400, italic: false };
export const DIRECTION_LABEL_FONT: FontSpec = { family: 'Inter', size: 11, weight: 600, italic: false };
export const DIRECTION_GOAL_FONT: FontSpec = { ...DIRECTION_BODY_FONT, weight: 600 };
export const GOAL_INDENT = 18;
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

const cut = (lines: string[], width: number, font: FontSpec, measurer: TextMeasurer): string[] => {
    const kept = lines.slice(0, directionMaxLines());
    let last = kept[kept.length - 1];
    while (last !== '' && measurer.width(last + ELLIPSIS, font) > width) last = last.slice(0, -1);
    kept[kept.length - 1] = last.trimEnd() + ELLIPSIS;
    return kept;
};

const words = (text: string): string => text.split(/\s+/).filter(Boolean).join(' ');

export function goalLines(goals: LayoutStatement[], width: number, measurer: TextMeasurer): { lines: string[]; marks: LineMark[] } {
    const lines: string[] = [];
    const marks: LineMark[] = [];
    goals.forEach((goal, index) => {
        const text = words(`${goal.title ?? ''} ${goal.body}`);
        const boldEnd = goal.title ? words(goal.title).length : 0;
        const prefix = `${index + 1}. `;
        let cursor = 0;
        wrapText(text, width - GOAL_INDENT, DIRECTION_GOAL_FONT, measurer).forEach((line, lineIndex) => {
            if (text[cursor] === ' ') cursor++;
            const bold = Math.max(0, Math.min(boldEnd, cursor + line.length) - cursor);
            cursor += line.length;
            lines.push(lineIndex === 0 ? prefix + line : line);
            marks.push({ number: lineIndex === 0 ? prefix.length : 0, bold });
        });
    });

    return { lines, marks };
}

export function layoutDirection(
    statements: LayoutStatement[],
    labels: { mission: string; vision: string; goals: string },
    measurer: TextMeasurer,
): DirectionColumn[] {
    const ordered = [...statements].sort((a, b) => a.position - b.position);
    const textOf = (kind: 'mission' | 'vision'): string[] => ordered.filter((statement) => statement.kind === kind).map((statement) => (statement.title ? `${statement.title}: ${statement.body}` : statement.body));

    let x = 0;
    return columnWidths.map(([key, width]) => {
        const textWidth = width - 2 * DIRECTION_PADDING;
        const goals = key === 'goals' ? goalLines(ordered.filter((statement) => statement.kind === 'goal'), textWidth, measurer) : null;
        const all = goals ? goals.lines : key === 'mission' || key === 'vision' ? textOf(key).flatMap((text) => wrapText(text, textWidth, DIRECTION_BODY_FONT, measurer)) : [];
        const truncated = all.length > directionMaxLines();
        const column: DirectionColumn = {
            key,
            x,
            width,
            label: key === 'logo' ? null : labels[key],
            lines: truncated ? cut(all, goals ? textWidth - GOAL_INDENT : textWidth, goals ? DIRECTION_GOAL_FONT : DIRECTION_BODY_FONT, measurer) : all,
            truncated,
            marks: goals ? goals.marks : [],
        };
        x += width;
        return column;
    });
}
