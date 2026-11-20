import { buildDomains, type TreeNode } from '../../layout/tree';
import { computeCanvasLayout } from '../../layout';
import type { AreaKey, AreaKind, ElementKind, ElementLayout, LayoutElement, LayoutWarning, TextMeasurer } from '../../layout/types';

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
    empty: boolean;
}

const flatten = (nodes: TreeNode[], depth: 0 | 1 | 2, rows: EntryRowData[]): void => {
    for (const node of nodes) {
        const kind = node.element.kind;
        const peers = nodes.filter((peer) => peer.element.kind === kind && !peer.element.unknownAllocation && !(peer.element as EntryElement).archived);
        const position = peers.indexOf(node);
        const row: EntryRowData = { element: node.element as EntryElement, depth, first: position === 0, last: position === peers.length - 1, closes: null, empty: kind === 'cluster' && node.children.length === 0 };
        rows.push(row);
        flatten(node.children, Math.min(depth + 1, 2) as 1 | 2, rows);
        if (depth === 0 && !node.element.unknownAllocation && !(node.element as EntryElement).archived) rows[rows.length - 1].closes = node.element as EntryElement;
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

export interface EntryAreaInfo {
    key: AreaKey;
    kind: string;
    label: string;
}

export interface ParentOption {
    value: string;
    label: string;
}

export const AREA_PREFIX = 'area:';

export const parentValue = (element: EntryElement): string => (element.parent === null ? `${AREA_PREFIX}${element.area}` : element.parent);

export function parentOptions(element: EntryElement, elements: EntryElement[], areas: EntryAreaInfo[]): ParentOption[] {
    const kind = areas.find(({ key }) => key === element.area)?.kind;
    const sameKind = areas.filter((area) => area.kind === kind);
    const live = elements.filter(({ archived }) => !archived);

    if (element.kind === 'domain') return sameKind.map(({ key, label }) => ({ value: `${AREA_PREFIX}${key}`, label }));

    return sameKind.flatMap((area) =>
        buildRows(live, area.key)
            .filter(({ element: candidate }) => candidate.kind === 'domain' ? !candidate.unknownAllocation : candidate.kind === 'cluster' && element.kind === 'activity')
            .map(({ element: candidate }) => ({ value: candidate.id, label: elementPath(candidate, elements, area.label) })),
    );
}

export const descendantNames = (element: EntryElement, elements: EntryElement[]): string[] =>
    elements.filter(({ parent }) => parent === element.id).flatMap((child) => [child.name, ...descendantNames(child, elements)]);

export const NAME_LIMIT = 5;

export interface EntryWarning {
    id: string;
    key: string;
    name: string;
    other?: string;
}

type ElementWarning = Exclude<LayoutWarning, { kind: 'directionTextCut' }>;

export function entryWarnings(elements: EntryElement[], areas: EntryAreaInfo[], area: AreaKey, measurer: TextMeasurer, layouts: Record<string, ElementLayout> = {}): EntryWarning[] {
    const layoutAreas = areas.map(({ key, kind, label }, index) => ({ key, kind: kind as AreaKind, label, position: index + 1 }));
    const { warnings } = computeCanvasLayout({ areas: layoutAreas, elements: elements.filter(({ archived }) => !archived), statements: [], directionLabels: { mission: '', vision: '', goals: '' }, layouts }, measurer);
    const list = areas.find(({ key }) => key === area)?.kind === 'list';
    const keys = { emptyCluster: 'entry.warning.empty_cluster', domainWithoutActivities: list ? 'entry.warning.group_without_items' : 'entry.warning.domain_without_activities', beyondArtboard: 'entry.warning.beyond_artboard', overlap: 'layout.warning.overlap', adjusted: 'layout.warning.adjusted' };
    const known = new Set(elements.map(({ id }) => id));

    return warnings
        .filter((warning): warning is ElementWarning => warning.kind !== 'directionTextCut' && warning.area === area && known.has(warning.id))
        .map((warning) => ({ id: warning.id, key: keys[warning.kind], name: warning.name, ...(warning.kind === 'overlap' ? { other: warning.other } : {}) }));
}

export function filterByName(elements: EntryElement[], text: string): EntryElement[] {
    const needle = text.trim().toLowerCase();
    if (needle === '') return elements;

    const keep = new Set<string>();
    for (const element of elements.filter(({ name }) => name.toLowerCase().includes(needle))) {
        for (let current: EntryElement | undefined = element; current && !keep.has(current.id); current = elements.find(({ id }) => id === current?.parent)) keep.add(current.id);
    }

    return elements.filter(({ id }) => keep.has(id));
}
