import { moveRefusal, type RuleNode } from '../../edit-engine/moveRules';
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
    const own = subtree(id, elements);
    const nodes: RuleNode[] = elements.flatMap((element) => {
        const areaKind = areas.find((area) => area.key === element.area)?.kind;

        return areaKind === undefined ? [] : [{ id: element.id, parentId: element.parent, kind: element.kind, areaKind, name: element.name, unknownAllocation: element.unknownAllocation === true }];
    });
    const dragged = nodes.find((node) => node.id === id);
    if (!dragged) return [];

    return elements
        .filter((candidate) => {
            if (own.has(candidate.id) || candidate.id === block.parent || candidate.kind === 'activity') return false;
            const node = nodes.find((entry) => entry.id === candidate.id);

            return node !== undefined && moveRefusal(nodes, dragged, node) === null;
        })
        .map((candidate) => ({ id: candidate.id, path: pathOf(candidate.id, areas, elements) }));
}
