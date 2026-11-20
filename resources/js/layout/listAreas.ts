import { AREA_HEADER, AREA_PADDING } from './arrangeAreas';
import type { Flow, FlowContext } from './flowDomains';
import type { TreeNode } from './tree';
import { wrapText } from './wrapText';
import type { AreaKey, DrawnBlock, ElementKind, ManualRect } from './types';

const GROUP_GAP = 12;
const ITEM_GAP = 4;
const ITEM_INDENT = 8;
const depth: Record<ElementKind, number> = { domain: 1, cluster: 2, activity: 3 };

export function flowList(groups: TreeNode[], area: AreaKey, innerWidth: number, context: FlowContext): Flow {
    const blocks: DrawnBlock[] = [];
    let y = 0;

    const draw = (node: TreeNode, x: number, top: number, width: number, own: ManualRect | null = null): DrawnBlock => {
        const { element } = node;
        const style = context.style(element, element.kind);
        const textLines = wrapText(element.name, width, style.font, context.measurer);
        const block: DrawnBlock = {
            id: element.id,
            kind: element.kind,
            areaKey: area,
            parentId: element.parent,
            x,
            y: top,
            width,
            drawnHeight: textLines.length * style.lineHeight,
            z: own?.z ?? depth[element.kind],
            textLines,
            modality: null,
            system: false,
            style,
        };
        blocks.push(block);
        for (const child of node.children) {
            const childRect = context.rect?.(child.element.id) ?? null;
            if (childRect) {
                draw(child, x + childRect.x, top + childRect.y, childRect.width, childRect);
                continue;
            }
            const item = draw(child, x + ITEM_INDENT, top + block.drawnHeight + ITEM_GAP, width - ITEM_INDENT);
            block.drawnHeight += ITEM_GAP + item.drawnHeight;
        }
        if (own) block.drawnHeight = own.height;
        return block;
    };

    const automatic = groups.filter((group) => !context.rect?.(group.element.id));
    for (const group of automatic) y += draw(group, 0, y, innerWidth).drawnHeight + GROUP_GAP;
    let height = automatic.length === 0 ? 0 : y - GROUP_GAP;

    for (const group of groups) {
        const rect = context.rect?.(group.element.id);
        if (!rect) continue;
        const top = rect.y - AREA_HEADER - AREA_PADDING;
        draw(group, rect.x - AREA_PADDING, top, rect.width, rect);
        height = Math.max(height, top + rect.height);
    }

    return { blocks, height };
}
