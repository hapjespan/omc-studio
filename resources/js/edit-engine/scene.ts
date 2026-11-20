import { SceneGraph } from '@open-pencil/scene-graph';
import { ARTBOARD, type DrawnBlock, type DrawnCanvas } from '../layout';
import { textOffset } from './fontCalibration';
import { blockPadding, MODALITY_BADGE } from '../layout/padding';

export const ARTBOARD_ID = 'artboard';
export const TEXT_SUFFIX = '#text';

export interface Place {
    x: number;
    y: number;
    width: number;
    height?: number;
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

const DIRECTION_PADDING = 16;
const LABEL_LINE = 14;
const LABEL_GAP = 8;
const BODY_LINE = 18;

function addDirectionText(graph: SceneGraph, canvas: DrawnCanvas, block: DrawnBlock): void {
    const text = (id: string, x: number, y: number, width: number, lines: string[], size: number, weight: number, lineHeight: number, hex: string) =>
        graph.createNodeWithId(`${block.id}-${id}${TEXT_SUFFIX}`, 'TEXT', block.id, {
            name: `${block.id}-${id}${TEXT_SUFFIX}`,
            x,
            y,
            width,
            height: lines.length * lineHeight,
            text: lines.join('\n'),
            fontFamily: 'Inter',
            fontSize: size,
            fontWeight: weight,
            lineHeight,
            textAutoResize: 'NONE',
            textAlignHorizontal: 'LEFT',
            fills: solid(hex),
        });
    for (const column of canvas.direction) {
        const width = column.width - 2 * DIRECTION_PADDING;
        if (column.label) text(`${column.key}-label`, column.x + DIRECTION_PADDING, DIRECTION_PADDING, width, [column.label.toUpperCase()], 11, 600, LABEL_LINE, block.style.labelColour);
        if (column.lines.length > 0) text(column.key, column.x + DIRECTION_PADDING, DIRECTION_PADDING + LABEL_LINE + LABEL_GAP, width, column.lines, 13, column.key === 'goals' ? 600 : 400, BODY_LINE, block.style.textColour);
    }
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
                    y: pad.y + textOffset(block.style.font),
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
            if (block.id === 'direction') addDirectionText(graph, canvas, block);
            add(block.id);
        }
    };
    add(ARTBOARD_ID);

    return graph;
}

export function readPlaces(graph: SceneGraph, canvas: DrawnCanvas, sized: Set<string> = new Set()): Map<string, Place> {
    const places = new Map<string, Place>();
    for (const block of canvas.blocks) {
        const node = graph.getNode(block.id);
        if (node) places.set(block.id, { x: Math.round(node.x), y: Math.round(node.y), width: Math.round(node.width), ...(sized.has(block.id) ? { height: Math.round(node.height) } : {}) });
    }
    return places;
}
