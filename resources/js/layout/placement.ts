import type { DrawnBlock, LayoutElement, LayoutWarning, ManualRect } from './types';

export const automaticStart = (cursor: number, hanging: number): number => Math.max(cursor, hanging);

export function adjustedWarnings(elements: LayoutElement[], blocks: DrawnBlock[], rect: (id: string) => ManualRect | null): LayoutWarning[] {
    const blockOf = new Map(blocks.map((block) => [block.id, block]));
    const childrenOf = new Map<string, LayoutElement[]>();
    for (const child of elements) {
        if (child.parent === null) continue;
        const siblings = childrenOf.get(child.parent);
        if (siblings) siblings.push(child);
        else childrenOf.set(child.parent, [child]);
    }

    return elements.flatMap((parent): LayoutWarning[] => {
        const own = rect(parent.id);
        const block = blockOf.get(parent.id);
        if (!own || !block || block.drawnHeight <= own.height) return [];
        const children = childrenOf.get(parent.id) ?? [];
        const mixed = children.some((child) => rect(child.id)) && children.some((child) => !rect(child.id));

        return mixed ? [{ kind: 'adjusted', id: parent.id, name: parent.name, area: parent.area }] : [];
    });
}
