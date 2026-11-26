export const GRID = 8;

export const snapToGrid = (value: number): number => Math.round(value / GRID) * GRID;

export function snapChanges<T extends Partial<Record<'x' | 'y' | 'width' | 'height', number>>>(changes: T): T {
    const snapped = { ...changes };
    for (const key of ['x', 'y', 'width', 'height'] as const) {
        const value = changes[key];
        if (value !== undefined) (snapped as Record<string, number>)[key] = snapToGrid(value);
    }
    return snapped;
}
