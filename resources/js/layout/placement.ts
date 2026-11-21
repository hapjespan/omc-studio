import type { DrawnBlock, LayoutElement, LayoutWarning, ManualRect } from './types';

export const automaticStart = (cursor: number, hanging: number): number => Math.max(cursor, hanging);

export function adjustedWarnings(elements: LayoutElement[], blocks: DrawnBlock[], rect: (id: string) => ManualRect | null): LayoutWarning[] {
    const blockOf = new Map(blocks.map((block) => [block.id, block]));

    return elements.flatMap((parent): LayoutWarning[] => {
        const own = rect(parent.id);
        const block = blockOf.get(parent.id);
        const children = elements.filter((child) => child.parent === parent.id);
        const mixed = children.some((child) => rect(child.id)) && children.some((child) => !rect(child.id));

        return own && block && mixed && block.drawnHeight > own.height ? [{ kind: 'adjusted', id: parent.id, name: parent.name, area: parent.area }] : [];
    });
}
