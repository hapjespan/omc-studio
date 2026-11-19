import type { DrawnBlock } from '../layout/types';

export type NavigationKey = 'ArrowUp' | 'ArrowDown' | 'ArrowLeft' | 'ArrowRight';

export const isNavigationKey = (key: string): key is NavigationKey => ['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(key);

export function navigate(blocks: DrawnBlock[], currentId: string, key: NavigationKey): string | null {
    const current = blocks.find((block) => block.id === currentId);
    if (!current) return null;
    const elements = blocks.filter((block) => block.kind !== 'area');
    const childrenOf = (parentId: string | null, areaKey: string): DrawnBlock[] => elements.filter((block) => block.parentId === parentId && block.areaKey === areaKey);

    if (current.kind === 'area') return key === 'ArrowDown' || key === 'ArrowRight' ? (childrenOf(null, current.areaKey)[0]?.id ?? null) : null;
    if (key === 'ArrowLeft') return current.parentId ?? current.areaKey;
    if (key === 'ArrowRight') return childrenOf(current.id, current.areaKey)[0]?.id ?? null;
    const siblings = childrenOf(current.parentId, current.areaKey);

    return siblings[siblings.indexOf(current) + (key === 'ArrowDown' ? 1 : -1)]?.id ?? null;
}
