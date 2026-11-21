import type { Page } from '@playwright/test';

export interface EngineBlock {
    id: string;
    x: number;
    y: number;
    width: number;
    drawnHeight: number;
    textLines: string[];
}

export interface Measured {
    id: string;
    x: number;
    y: number;
    width: number;
    height: number;
    lines: string[];
    widest: number;
}

export const TOLERANCE = 1;

export async function readPicture(page: Page): Promise<{ engine: EngineBlock[]; measured: Measured[] }> {
    return page.evaluate(() => {
        const artboard = document.querySelector('[data-artboard]')!.getBoundingClientRect();
        const engine = JSON.parse(document.querySelector('[data-engine]')!.getAttribute('data-engine') ?? '[]');
        const measured = [...document.querySelectorAll<HTMLElement>('[data-block]')].map((node) => {
            const box = node.getBoundingClientRect();
            const spans = [...node.querySelectorAll<HTMLElement>('[data-line]')];
            const range = document.createRange();
            const widths = spans.map((span) => {
                range.selectNodeContents(span);
                return range.getBoundingClientRect().width;
            });
            return {
                id: node.dataset.block ?? '',
                x: box.left - artboard.left,
                y: box.top - artboard.top,
                width: box.width,
                height: box.height,
                lines: spans.map((span) => span.textContent ?? ''),
                widest: Math.max(0, ...widths),
            };
        });
        return { engine, measured };
    });
}

export function pictureDifferences(engine: EngineBlock[], measured: Measured[], tolerance = TOLERANCE): string[] {
    const found = new Map(measured.map((block) => [block.id, block]));
    const problems: string[] = [];
    for (const expected of engine) {
        const actual = found.get(expected.id);
        if (!actual) {
            problems.push(`${expected.id}: not drawn`);
            continue;
        }
        const deltas = { x: actual.x - expected.x, y: actual.y - expected.y, width: actual.width - expected.width, height: actual.height - expected.drawnHeight };
        for (const [axis, delta] of Object.entries(deltas)) {
            if (Math.abs(delta) > tolerance) problems.push(`${expected.id}: ${axis} differs by ${delta.toFixed(2)}`);
        }
        if (JSON.stringify(actual.lines) !== JSON.stringify(expected.textLines)) problems.push(`${expected.id}: text lines differ`);
        if (expected.id !== 'direction' && actual.widest > actual.width + tolerance) problems.push(`${expected.id}: a line is wider than the block`);
    }
    return problems;
}
