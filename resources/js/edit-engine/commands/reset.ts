import type { LayoutElement } from '../../layout';

export type ResetScope = 'element' | 'domain' | 'canvas';

export function resetIds(elements: LayoutElement[], scope: ResetScope, id: string | null): string[] {
    if (scope === 'canvas') return elements.map((element) => element.id);
    if (id === null) return [];
    if (scope === 'element') return elements.some((element) => element.id === id) ? [id] : [];
    const found = new Set([id]);
    for (const element of elements) if (element.parent !== null && found.has(element.parent)) found.add(element.id);

    return elements.filter((element) => found.has(element.id)).map((element) => element.id);
}
