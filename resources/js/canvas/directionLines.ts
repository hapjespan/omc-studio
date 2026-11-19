export interface DirectionSegment {
    bold: string;
    rest: string;
}

export function splitDirectionLines(lines: string[], boldLengths: number[]): DirectionSegment[] {
    let remaining = 0;

    return lines.map((line) => {
        const start = /^(\d+)\. /.exec(line);
        if (start) remaining = boldLengths[Number(start[1]) - 1] ?? 0;
        const take = Math.min(remaining, line.length);
        remaining = Math.max(0, remaining - take - 1);

        return { bold: line.slice(0, take), rest: line.slice(take) };
    });
}
