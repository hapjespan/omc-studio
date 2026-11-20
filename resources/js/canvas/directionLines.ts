import type { LineMark } from '../layout/types';

export interface DirectionSegment {
    number: string;
    bold: string;
    rest: string;
}

export function splitDirectionLine(line: string, mark?: LineMark): DirectionSegment {
    const number = line.slice(0, mark?.number ?? 0);
    const text = line.slice(mark?.number ?? 0);
    const bold = Math.min(mark?.bold ?? 0, text.length);

    return { number, bold: text.slice(0, bold), rest: text.slice(bold) };
}
