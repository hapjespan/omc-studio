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
    closes: EntryElement | null;
}

const flatten = (nodes: TreeNode[], depth: 0 | 1 | 2, rows: EntryRowData[]): void => {
    for (const node of nodes) {
        const kind = node.element.kind;
        const peers = nodes.filter((peer) => peer.element.kind === kind && !peer.element.unknownAllocation);
        const position = peers.indexOf(node);
        const row: EntryRowData = { element: node.element as EntryElement, depth, first: position === 0, last: position === peers.length - 1, closes: null };
        rows.push(row);
        flatten(node.children, Math.min(depth + 1, 2) as 1 | 2, rows);
        if (depth === 0) rows[rows.length - 1].closes = node.element as EntryElement;
    }
};

export function buildRows(elements: EntryElement[], area: AreaKey): EntryRowData[] {
    const rows: EntryRowData[] = [];
    flatten(buildDomains(elements, area), 0, rows);

    return rows;
}

export const kindLabelKey = (kind: ElementKind, area: AreaKey): string => (kind === 'cluster' ? 'entry.kind.cluster' : `areas.${area}.level_${kind === 'domain' ? 1 : 2}`);

export const createdElement = (elements: EntryElement[], area: AreaKey, kind: ElementKind, name: string, parent: string | null): EntryElement | undefined =>
    elements.find((candidate) => !candidate.archived && candidate.area === area && candidate.kind === kind && candidate.name === name && candidate.parent === parent);

export const firstError = (errors: object): string | undefined => {
    const messages = errors as Record<string, string | undefined>;

    return messages.name ?? messages.rule;
};

export const elementPath = (element: EntryElement, elements: EntryElement[], areaLabel: string): string => {
    const names: string[] = [];
    for (let current: EntryElement | undefined = element; current; current = elements.find((candidate) => candidate.id === current?.parent)) names.unshift(current.name);

    return [areaLabel, ...names].join(' > ');
};
