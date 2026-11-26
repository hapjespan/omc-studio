import type { AreaKind, ElementKind } from '../layout';

export type RefusalReason = 'other_kind' | 'unknown_allocation' | 'name_taken' | 'cluster_needs_domain' | 'no_clusters_in_list';

export interface RuleNode {
    id: string;
    parentId: string | null;
    kind: ElementKind;
    areaKind: AreaKind;
    name: string;
    unknownAllocation: boolean;
}

export const normaliseName = (name: string): string => name.trim().toLowerCase();

export function moveRefusal(nodes: RuleNode[], dragged: RuleNode, target: RuleNode): RefusalReason | null {
    if (dragged.kind === 'cluster' && target.kind !== 'domain') return 'cluster_needs_domain';
    if (dragged.kind === 'cluster' && target.areaKind === 'list') return 'no_clusters_in_list';
    if (target.areaKind !== dragged.areaKind) return 'other_kind';
    if (target.unknownAllocation) return 'unknown_allocation';
    if (nodes.some((node) => node.parentId === target.id && node.id !== dragged.id && normaliseName(node.name) === normaliseName(dragged.name))) return 'name_taken';

    return null;
}
