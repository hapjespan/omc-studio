import type { Selection } from '../transform';

export function forward({ editor, ids }: Selection): void {
    if (ids.length > 0) editor.bringForward();
}

export function backward({ editor, ids }: Selection): void {
    if (ids.length > 0) editor.sendBackward();
}

export function stackOrders(editor: Selection['editor'], parentIds: string[], isText: (id: string) => boolean, include: (id: string) => boolean = () => true): Map<string, number> {
    const orders = new Map<string, number>();
    for (const parentId of parentIds) {
        const parent = editor.graph.getNode(parentId);
        (parent?.childIds ?? []).filter((id) => !isText(id)).forEach((id, index) => include(id) && orders.set(id, index));
    }
    return orders;
}
