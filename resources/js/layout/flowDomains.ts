import { defaultColours } from '../colour';
import { styleBackdrop } from './resolveStyle';
import { stackOrder } from './manual';
import { elementPadding, MODALITY_BADGE } from './padding';
import { automaticStart } from './placement';
import { AREA_HEADER, AREA_PADDING } from './arrangeAreas';
import type { TreeNode } from './tree';
import { wrapText } from './wrapText';
import type { AreaKey, BlockStyle, DrawnBlock, ElementKind, LayoutElement, ManualRect, TextMeasurer } from './types';

export interface FlowContext {
    measurer: TextMeasurer;
    style: (element: LayoutElement, level: ElementKind, behind: string) => BlockStyle;
    behind?: string;
    rect?: (id: string) => ManualRect | null;
}

export interface Flow {
    blocks: DrawnBlock[];
    height: number;
}

export const CARD_MIN_WIDTH = 260;
export const CARD_GAP = 12;
const CHILD_GAP = 6;
const CLUSTER_INDENT = 8;
export const AMOUNT_ROOM = 39;
const depth: Record<ElementKind, number> = { domain: 1, cluster: 2, activity: 3 };

export const columnCount = (width: number): number => Math.max(1, Math.floor((width + CARD_GAP) / (CARD_MIN_WIDTH + CARD_GAP)));

function place(node: TreeNode, area: AreaKey, x: number, y: number, width: number, out: DrawnBlock[], context: FlowContext, own: ManualRect | null = null, behind: string = context.behind ?? defaultColours.white): DrawnBlock {
    const { element } = node;
    const pad = elementPadding[element.kind];
    const style = context.style(element, element.kind, behind);
    const backdrop = styleBackdrop(style, behind);
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
        z: stackOrder(depth[element.kind], own),
        textLines,
        modality: element.modality,
        system: !!element.unknownAllocation,
        style,
    };
    out.push(block);

    let cursor = pad.y + textLines.length * style.lineHeight;
    let hanging = 0;
    for (const child of node.children) {
        const childRect = context.rect?.(child.element.id) ?? null;
        if (!childRect) continue;
        const placed = place(child, area, x + childRect.x, y + childRect.y, childRect.width, out, context, childRect, backdrop);
        hanging = Math.max(hanging, childRect.y + placed.drawnHeight);
    }
    cursor = automaticStart(cursor, hanging);
    for (const child of node.children) {
        if (context.rect?.(child.element.id)) continue;
        const indent = child.element.kind === 'cluster' ? CLUSTER_INDENT : 0;
        const placed = place(child, area, x + pad.x + indent, y + cursor + CHILD_GAP, width - 2 * pad.x - indent, out, context, null, backdrop);
        cursor += CHILD_GAP + placed.drawnHeight;
    }
    block.drawnHeight = Math.max(own?.height ?? 0, element.kind === 'domain' ? Math.max(2 * pad.y + textLines.length * style.lineHeight + AMOUNT_ROOM, cursor + pad.y) : cursor + pad.y);

    return block;
}

export function flowDomains(domains: TreeNode[], area: AreaKey, innerWidth: number, context: FlowContext): Flow {
    const columns = columnCount(innerWidth);
    const cardWidth = Math.floor((innerWidth - (columns - 1) * CARD_GAP) / columns);
    const blocks: DrawnBlock[] = [];
    const automatic = domains.filter((domain) => !context.rect?.(domain.element.id));
    const hanging = automatic.length === 0 ? 0 : domains.reduce((lowest, domain) => {
        const rect = context.rect?.(domain.element.id);
        if (!rect) return lowest;
        const top = rect.y - AREA_HEADER - AREA_PADDING;

        return Math.max(lowest, top + place(domain, area, rect.x - AREA_PADDING, top, rect.width, [], context, rect).drawnHeight + CARD_GAP);
    }, 0);
    let y = automaticStart(0, hanging);

    for (let start = 0; start < automatic.length; start += columns) {
        const row = automatic.slice(start, start + columns).map((domain, index) => place(domain, area, index * (cardWidth + CARD_GAP), y, cardWidth, blocks, context));
        const rowHeight = Math.max(...row.map((card) => card.drawnHeight));
        y += rowHeight + CARD_GAP;
    }
    let height = automatic.length === 0 ? 0 : y - CARD_GAP;

    for (const domain of domains) {
        const rect = context.rect?.(domain.element.id);
        if (!rect) continue;
        const top = rect.y - AREA_HEADER - AREA_PADDING;
        const placed = place(domain, area, rect.x - AREA_PADDING, top, rect.width, blocks, context, rect);
        height = Math.max(height, top + placed.drawnHeight);
    }

    return { blocks, height };
}
