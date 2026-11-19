import { breakLines, type FontSpec, type TextMeasurer } from './linebreak';

export interface FixtureBlock {
    id: string;
    kind: 'direction' | 'logo' | 'domain' | 'cluster' | 'activity';
    parent: string | null;
    x: number;
    y: number;
    width: number;
    height?: number;
    fill: string;
    text: string;
    fontFamily: string;
    fontSize: number;
    fontWeight: number;
    textColor: string;
    area?: string;
}

export interface Fixture {
    artboard: { width: number; height: number };
    padding: number;
    lineHeightFactor: number;
    blocks: FixtureBlock[];
}

export interface Block {
    id: string;
    kind: FixtureBlock['kind'];
    parent: string | null;
    x: number;
    y: number;
    width: number;
    height: number;
    fill: string;
    textColor: string;
    font: FontSpec;
    lineHeight: number;
    padding: number;
    lines: string[];
    area?: string;
    name: string;
}

export function computeBlocks(fixture: Fixture, measurer: TextMeasurer): Block[] {
    return fixture.blocks.map((block) => {
        const font = { family: block.fontFamily, size: block.fontSize, weight: block.fontWeight };
        const lineHeight = Math.round(block.fontSize * fixture.lineHeightFactor);
        const lines = block.text === '' ? [] : breakLines(block.text, block.width - 2 * fixture.padding, font, measurer);
        return {
            id: block.id,
            kind: block.kind,
            parent: block.parent,
            x: block.x,
            y: block.y,
            width: block.width,
            height: block.height ?? 2 * fixture.padding + lines.length * lineHeight,
            fill: block.fill,
            textColor: block.textColor,
            font,
            lineHeight,
            padding: fixture.padding,
            lines,
            area: block.area,
            name: block.text,
        };
    });
}

export function absolutePosition(blocks: Block[], id: string): { x: number; y: number } {
    const byId = new Map(blocks.map((block) => [block.id, block]));
    let x = 0;
    let y = 0;
    for (let block = byId.get(id); block; block = block.parent ? byId.get(block.parent) : undefined) {
        x += block.x;
        y += block.y;
    }
    return { x, y };
}
