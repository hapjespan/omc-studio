import { buildDomains, type TreeNode } from '../../layout/tree';
import type { AreaKey, ElementKind, LayoutElement } from '../../layout/types';

export interface EntryElement extends LayoutElement {
    description: string | null;
    rationale: string | null;
    icon: string | null;
    unknownAllocation: boolean;
    archived: boolean;
}

export interface EntryRowData {
    element: EntryElement;
    depth: 0 | 1 | 2;
    first: boolean;
    last: boolean;
}

const flatten = (nodes: TreeNode[], depth: 0 | 1 | 2, rows: EntryRowData[]): void => {
    for (const node of nodes) {
        const kind = node.element.kind;
        const peers = nodes.filter((peer) => peer.element.kind === kind && !peer.element.unknownAllocation);
        const position = peers.indexOf(node);
        rows.push({ element: node.element as EntryElement, depth, first: position === 0, last: position === peers.length - 1 });
        flatten(node.children, Math.min(depth + 1, 2) as 1 | 2, rows);
    }
};

export function buildRows(elements: EntryElement[], area: AreaKey): EntryRowData[] {
    const rows: EntryRowData[] = [];
    flatten(buildDomains(elements, area), 0, rows);

    return rows;
}

export const kindLabelKey = (kind: ElementKind, area: AreaKey): string => (kind === 'cluster' ? 'entry.kind.cluster' : `areas.${area}.level_${kind === 'domain' ? 1 : 2}`);
