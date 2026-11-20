import { SceneGraph } from '@open-pencil/scene-graph';
import { ARTBOARD, type DrawnBlock, type DrawnCanvas } from '../layout';
import { blockPadding, MODALITY_BADGE } from '../layout/padding';

export const ARTBOARD_ID = 'artboard';
export const TEXT_SUFFIX = '#text';

export interface Place {
    x: number;
    y: number;
    width: number;
}

function colour(hex: string) {
    const value = parseInt(hex.slice(1), 16);
    return { r: ((value >> 16) & 255) / 255, g: ((value >> 8) & 255) / 255, b: (value & 255) / 255, a: 1 };
}

const solid = (hex: string) => [{ type: 'SOLID' as const, color: colour(hex), opacity: 1, visible: true }];

const stroke = (hex: string) => [{ type: 'SOLID' as const, color: colour(hex), opacity: 1, visible: true, weight: 1, align: 'INSIDE' as const }];

function parentId(block: DrawnBlock, byId: Map<string, DrawnBlock>): string {
    if (block.kind === 'area') return ARTBOARD_ID;
    return byId.has(block.parentId ?? block.areaKey) ? (block.parentId ?? block.areaKey) : ARTBOARD_ID;
}

export function placesOf(canvas: DrawnCanvas): Map<string, Place> {
    const byId = new Map(canvas.blocks.map((block) => [block.id, block]));
    const places = new Map<string, Place>();
    for (const block of canvas.blocks) {
        const parent = byId.get(parentId(block, byId));
        places.set(block.id, { x: Math.round(block.x - (parent?.x ?? 0)), y: Math.round(block.y - (parent?.y ?? 0)), width: Math.round(block.width) });
    }
    return places;
}

export function buildScene(canvas: DrawnCanvas): SceneGraph {
    const graph = new SceneGraph();
    const page = graph.getPages()[0] ?? graph.addPage('Artboard');
    graph.createNodeWithId(ARTBOARD_ID, 'FRAME', page.id, { name: ARTBOARD_ID, x: 0, y: 0, width: ARTBOARD.width, height: ARTBOARD.height, fills: solid('#ffffff'), clipsContent: true });

    const byId = new Map(canvas.blocks.map((block) => [block.id, block]));
    const places = placesOf(canvas);
    const children = new Map<string, DrawnBlock[]>();
    for (const block of canvas.blocks) {
        const parent = parentId(block, byId);
        const siblings = children.get(parent);
        if (siblings) siblings.push(block);
        else children.set(parent, [block]);
    }

    const add = (parent: string) => {
        for (const block of [...(children.get(parent) ?? [])].sort((one, other) => one.z - other.z)) {
            const place = places.get(block.id)!;
            graph.createNodeWithId(block.id, 'FRAME', parent, {
                name: block.id,
                x: place.x,
                y: place.y,
                width: place.width,
                height: block.drawnHeight,
                fills: block.style.fill ? solid(block.style.fill) : [],
                strokes: block.style.border ? stroke(block.style.border) : [],
                cornerRadius: block.style.radius,
                clipsContent: false,
            });
            if (block.textLines.length > 0) {
                const pad = blockPadding(block);
                graph.createNodeWithId(block.id + TEXT_SUFFIX, 'TEXT', block.id, {
                    name: block.id + TEXT_SUFFIX,
                    x: pad.x,
                    y: pad.y,
                    width: place.width - 2 * pad.x - (block.modality ? MODALITY_BADGE : 0),
                    height: block.textLines.length * block.style.lineHeight,
                    text: block.textLines.join('\n'),
                    fontFamily: block.style.font.family,
                    fontSize: block.style.font.size,
                    fontWeight: block.style.font.weight,
                    italic: block.style.font.italic,
                    lineHeight: block.style.lineHeight,
                    textAutoResize: 'NONE',
                    textAlignHorizontal: 'LEFT',
                    fills: solid(block.style.textColour),
                });
            }
            add(block.id);
        }
    };
    add(ARTBOARD_ID);

    return graph;
}

export function readPlaces(graph: SceneGraph, canvas: DrawnCanvas): Map<string, Place> {
    const places = new Map<string, Place>();
    for (const block of canvas.blocks) {
        const node = graph.getNode(block.id);
        if (node) places.set(block.id, { x: Math.round(node.x), y: Math.round(node.y), width: Math.round(node.width) });
    }
    return places;
}
