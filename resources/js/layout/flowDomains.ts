import type { TreeNode } from './tree';
import { wrapText } from './wrapText';
import type { AreaKey, BlockStyle, DrawnBlock, ElementKind, LayoutElement, TextMeasurer } from './types';

export interface FlowContext {
    measurer: TextMeasurer;
    style: (element: LayoutElement, level: ElementKind) => BlockStyle;
}

export interface Flow {
    blocks: DrawnBlock[];
    height: number;
}

export const CARD_MIN_WIDTH = 260;
export const CARD_GAP = 12;
const CHILD_GAP = 6;
const CLUSTER_INDENT = 8;
const MODALITY_BADGE = 22;
const padding: Record<ElementKind, { x: number; y: number }> = {
    domain: { x: 12, y: 12 },
    cluster: { x: 8, y: 8 },
    activity: { x: 8, y: 6 },
};
const depth: Record<ElementKind, number> = { domain: 1, cluster: 2, activity: 3 };

export const columnCount = (width: number): number => Math.max(1, Math.floor((width + CARD_GAP) / (CARD_MIN_WIDTH + CARD_GAP)));

function place(node: TreeNode, area: AreaKey, x: number, y: number, width: number, out: DrawnBlock[], context: FlowContext): DrawnBlock {
    const { element } = node;
    const pad = padding[element.kind];
    const style = context.style(element, element.kind);
    const textWidth = width - 2 * pad.x - (element.modality ? MODALITY_BADGE : 0);
    const textLines = wrapText(element.name, textWidth, style.font, context.measurer);
    const block: DrawnBlock = {
        id: element.id,
        kind: element.kind,
        areaKey: area,
        parentId: element.parent,
        x,
        y,
        width,
        drawnHeight: 0,
        z: depth[element.kind],
        textLines,
        modality: element.modality,
        system: !!element.unknownAllocation,
        style,
    };
    out.push(block);

    let cursor = pad.y + textLines.length * style.lineHeight;
    for (const child of node.children) {
        const indent = child.element.kind === 'cluster' ? CLUSTER_INDENT : 0;
        const placed = place(child, area, x + pad.x + indent, y + cursor + CHILD_GAP, width - 2 * pad.x - indent, out, context);
        cursor += CHILD_GAP + placed.drawnHeight;
    }
    block.drawnHeight = cursor + pad.y;

    return block;
}

export function flowDomains(domains: TreeNode[], area: AreaKey, innerWidth: number, context: FlowContext): Flow {
    const columns = columnCount(innerWidth);
    const cardWidth = Math.floor((innerWidth - (columns - 1) * CARD_GAP) / columns);
    const blocks: DrawnBlock[] = [];
    let y = 0;

    for (let start = 0; start < domains.length; start += columns) {
        const row = domains.slice(start, start + columns).map((domain, index) => place(domain, area, index * (cardWidth + CARD_GAP), y, cardWidth, blocks, context));
        const rowHeight = Math.max(...row.map((card) => card.drawnHeight));
        row.forEach((card) => (card.drawnHeight = rowHeight));
        y += rowHeight + CARD_GAP;
    }

    return { blocks, height: domains.length === 0 ? 0 : y - CARD_GAP };
}
