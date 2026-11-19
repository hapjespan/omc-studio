import { compareUnknownLast } from './unknownAllocation';
import type { AreaKey, LayoutElement } from './types';

export interface TreeNode {
    element: LayoutElement;
    children: TreeNode[];
}

const byPosition = (a: LayoutElement, b: LayoutElement): number => a.position - b.position || a.id.localeCompare(b.id);

export function buildDomains(elements: LayoutElement[], area: AreaKey): TreeNode[] {
    const childrenOf = (parent: string): TreeNode[] =>
        elements
            .filter((candidate) => candidate.parent === parent)
            .sort((a, b) => Number(a.kind === 'cluster') - Number(b.kind === 'cluster') || byPosition(a, b))
            .map((element) => ({ element, children: childrenOf(element.id) }));

    return elements
        .filter((candidate) => candidate.area === area && candidate.parent === null && candidate.kind === 'domain')
        .sort((a, b) => compareUnknownLast(a, b) || byPosition(a, b))
        .map((element) => ({ element, children: childrenOf(element.id) }));
}
