import { compareUnknownLast } from './unknownAllocation';
import type { AreaKey, LayoutElement } from './types';

export interface TreeNode {
    element: LayoutElement;
    children: TreeNode[];
}

const byPosition = (a: LayoutElement, b: LayoutElement): number => a.position - b.position || (a.id < b.id ? -1 : a.id > b.id ? 1 : 0);

export function buildDomains(elements: LayoutElement[], area: AreaKey): TreeNode[] {
    const byParent = new Map<string, LayoutElement[]>();
    const roots: LayoutElement[] = [];
    for (const candidate of elements) {
        if (candidate.parent === null) {
            if (candidate.area === area && candidate.kind === 'domain') roots.push(candidate);
            continue;
        }
        const siblings = byParent.get(candidate.parent);
        if (siblings) siblings.push(candidate);
        else byParent.set(candidate.parent, [candidate]);
    }
    const node = (element: LayoutElement): TreeNode => ({
        element,
        children: (byParent.get(element.id) ?? []).sort((a, b) => Number(a.kind === 'cluster') - Number(b.kind === 'cluster') || byPosition(a, b)).map(node),
    });

    return roots.sort((a, b) => compareUnknownLast(a, b) || byPosition(a, b)).map(node);
}
