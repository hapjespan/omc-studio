import type { Flow, FlowContext } from './flowDomains';
import type { TreeNode } from './tree';
import { wrapText } from './wrapText';
import type { AreaKey, DrawnBlock, ElementKind } from './types';

const GROUP_GAP = 12;
const ITEM_GAP = 4;
const ITEM_INDENT = 8;
const depth: Record<ElementKind, number> = { domain: 1, cluster: 2, activity: 3 };

export function flowList(groups: TreeNode[], area: AreaKey, innerWidth: number, context: FlowContext): Flow {
    const blocks: DrawnBlock[] = [];
    let y = 0;

    const draw = (node: TreeNode, x: number, top: number, width: number): DrawnBlock => {
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
            z: depth[element.kind],
            textLines,
            modality: null,
            system: false,
            style,
        };
        blocks.push(block);
        for (const child of node.children) {
            const item = draw(child, x + ITEM_INDENT, top + block.drawnHeight + ITEM_GAP, width - ITEM_INDENT);
            block.drawnHeight += ITEM_GAP + item.drawnHeight;
        }
        return block;
    };

    for (const group of groups) y += draw(group, 0, y, innerWidth).drawnHeight + GROUP_GAP;

    return { blocks, height: groups.length === 0 ? 0 : y - GROUP_GAP };
}
