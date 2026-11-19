import type { SceneGraph, SceneNode } from '@open-pencil/scene-graph';
import type { Block, Fixture } from './layout';

export const TEXT_SUFFIX = '#text';

export function hexToColor(hex: string) {
    const value = parseInt(hex.slice(1), 16);
    return { r: ((value >> 16) & 255) / 255, g: ((value >> 8) & 255) / 255, b: (value & 255) / 255, a: 1 };
}

export function colorToHex(color: { r: number; g: number; b: number }) {
    const part = (channel: number) => Math.round(channel * 255).toString(16).padStart(2, '0');
    return `#${part(color.r)}${part(color.g)}${part(color.b)}`;
}

const solid = (hex: string) => [{ type: 'SOLID' as const, color: hexToColor(hex), opacity: 1, visible: true }];

export function buildScene(graph: SceneGraph, fixture: Fixture, blocks: Block[]): SceneNode {
    const page = graph.getPages()[0] ?? graph.addPage('Artboard');
    const artboard = graph.createNodeWithId('artboard', 'FRAME', page.id, {
        name: 'artboard',
        x: 0,
        y: 0,
        width: fixture.artboard.width,
        height: fixture.artboard.height,
        fills: solid('#ffffff'),
        clipsContent: true,
    });
    for (const block of blocks) {
        const parentId = block.parent ?? artboard.id;
        graph.createNodeWithId(block.id, 'FRAME', parentId, {
            name: block.id,
            x: block.x,
            y: block.y,
            width: block.width,
            height: block.height,
            fills: solid(block.fill),
            clipsContent: false,
        });
        if (block.lines.length === 0) continue;
        graph.createNodeWithId(block.id + TEXT_SUFFIX, 'TEXT', block.id, {
            name: block.id + TEXT_SUFFIX,
            x: block.padding,
            y: block.padding,
            width: block.width - 2 * block.padding,
            height: block.lines.length * block.lineHeight,
            text: block.lines.join('\n'),
            fontFamily: block.font.family,
            fontSize: block.font.size,
            fontWeight: block.font.weight,
            lineHeight: block.lineHeight,
            textAutoResize: 'NONE',
            textAlignHorizontal: 'LEFT',
            fills: solid(block.textColor),
        });
    }
    return artboard;
}
