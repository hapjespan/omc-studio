import type { SceneGraph } from '@open-pencil/scene-graph';
import { colorToHex, TEXT_SUFFIX } from './scene';

export interface ReadBlock {
    id: string;
    parent: string | null;
    x: number;
    y: number;
    width: number;
    height: number;
    fill: string;
    textColor: string | null;
    fontFamily: string | null;
    fontSize: number | null;
    fontWeight: number | null;
    lines: string[];
}

export function readBlocks(graph: SceneGraph): ReadBlock[] {
    const artboard = graph.getNode('artboard');
    if (!artboard) return [];
    const result: ReadBlock[] = [];
    const visit = (id: string, parent: string | null) => {
        const node = graph.getNode(id)!;
        const textNode = graph.getNode(id + TEXT_SUFFIX);
        result.push({
            id,
            parent,
            x: node.x,
            y: node.y,
            width: node.width,
            height: node.height,
            fill: colorToHex(node.fills[0].color),
            textColor: textNode ? colorToHex(textNode.fills[0].color) : null,
            fontFamily: textNode?.fontFamily ?? null,
            fontSize: textNode?.fontSize ?? null,
            fontWeight: textNode?.fontWeight ?? null,
            lines: textNode ? textNode.text.split('\n') : [],
        });
        for (const childId of node.childIds) {
            if (!childId.endsWith(TEXT_SUFFIX)) visit(childId, id);
        }
    };
    for (const childId of artboard.childIds) visit(childId, null);
    return result;
}
