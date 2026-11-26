import { ARTBOARD_HEIGHT } from './arrangeAreas';
import type { TreeNode } from './tree';
import type { AreaKey, DrawnBlock, LayoutWarning } from './types';

const countActivities = (node: TreeNode): number => node.children.reduce((sum, child) => sum + (child.element.kind === 'activity' ? 1 : countActivities(child)), 0);

const intersects = (a: DrawnBlock, b: DrawnBlock): boolean => a.x < b.x + b.width && b.x < a.x + a.width && a.y < b.y + b.drawnHeight && b.y < a.y + a.drawnHeight;

function overlapWarnings(blocks: DrawnBlock[], names: Map<string, string>): LayoutWarning[] {
    const siblings = Map.groupBy(blocks.filter((block) => block.areaKey !== 'direction'), (block) => (block.kind === 'area' ? 'areas' : `${block.areaKey}|${block.parentId}`));

    return [...siblings.values()].flatMap((group) =>
        group.flatMap((block, index) =>
            group
                .slice(index + 1)
                .filter((other) => intersects(block, other))
                .map((other): LayoutWarning => ({ kind: 'overlap', id: block.id, name: names.get(block.id) ?? '', otherId: other.id, other: names.get(other.id) ?? '', area: block.areaKey })),
        ),
    );
}

export function computeWarnings(domainsByArea: Map<AreaKey, TreeNode[]>, blocks: DrawnBlock[], names: Map<string, string>): { warnings: LayoutWarning[]; beyondArtboardCount: number } {
    const blockOf = new Map(blocks.map((block) => [block.id, block]));
    const warnings: LayoutWarning[] = [];

    for (const [area, domains] of domainsByArea) {
        for (const domain of domains) {
            const { id, name } = domain.element;
            const block = blockOf.get(id);
            if (!block?.system && countActivities(domain) === 0) warnings.push({ kind: 'domainWithoutActivities', id, name, area });
            for (const cluster of domain.children.filter((child) => child.element.kind === 'cluster' && child.children.length === 0)) {
                warnings.push({ kind: 'emptyCluster', id: cluster.element.id, name: cluster.element.name, area });
            }
            if (block && block.y + block.drawnHeight > ARTBOARD_HEIGHT) warnings.push({ kind: 'beyondArtboard', id, name, area });
        }
    }

    const beyondArtboardCount = warnings.filter((warning) => warning.kind === 'beyondArtboard').length;

    return { warnings: [...warnings, ...overlapWarnings(blocks, names)], beyondArtboardCount };
}
