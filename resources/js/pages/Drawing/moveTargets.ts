import type { LayoutArea, LayoutElement } from '../../layout';

export interface MoveTarget {
    id: string;
    path: string;
}

export function pathOf(id: string, areas: LayoutArea[], elements: LayoutElement[]): string {
    const names: string[] = [];
    let current = elements.find((element) => element.id === id);
    const area = areas.find((candidate) => candidate.key === (current?.area ?? id));
    while (current) {
        names.unshift(current.name);
        current = elements.find((element) => element.id === current?.parent);
    }

    return [area?.label ?? '', ...names].filter(Boolean).join(' › ');
}

const subtree = (id: string, elements: LayoutElement[]): Set<string> => {
    const found = new Set([id]);
    for (const element of elements) if (element.parent !== null && found.has(element.parent)) found.add(element.id);

    return found;
};

export function moveTargets(id: string, areas: LayoutArea[], elements: LayoutElement[]): MoveTarget[] {
    const block = elements.find((element) => element.id === id);
    if (!block || block.kind === 'domain') return [];
    const kindOf = (key: string) => areas.find((area) => area.key === key)?.kind;
    const own = subtree(id, elements);

    return elements
        .filter((candidate) => {
            if (own.has(candidate.id) || candidate.id === block.parent || candidate.unknownAllocation) return false;
            if (candidate.kind === 'activity' || kindOf(candidate.area) !== kindOf(block.area)) return false;
            if (block.kind === 'cluster' && candidate.kind !== 'domain') return false;
            if (candidate.kind === 'cluster' && kindOf(candidate.area) === 'list') return false;

            return !elements.some((sibling) => sibling.parent === candidate.id && sibling.id !== id && sibling.name.toLowerCase() === block.name.toLowerCase());
        })
        .map((candidate) => ({ id: candidate.id, path: pathOf(candidate.id, areas, elements) }));
}
