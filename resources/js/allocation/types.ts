import type { TargetNode } from './helpers';

export interface TargetRow extends TargetNode {
    name: string;
    area: string | null;
    totalCents: number | null;
    children: TargetRow[];
}

export interface CostRecordRow {
    id: string;
    code: string | null;
    name: string;
    archived: boolean;
    costKind: string | null;
    owner: string | null;
    budgetCents: number;
    status: 'allocated' | 'unallocated';
}

export interface DraftLine {
    elementId: string;
    basisPoints: number;
}

export interface TargetInfo {
    id: string;
    name: string;
    kind: 'domain' | 'cluster' | 'activity';
    path: string;
    area: string | null;
}

export function indexTargets(rows: TargetRow[], parents: string[] = [], into: Record<string, TargetInfo> = {}): Record<string, TargetInfo> {
    for (const row of rows) {
        into[String(row.id)] = { id: String(row.id), name: row.name, kind: row.kind, path: [...parents, row.name].join(' > '), area: row.area };
        indexTargets(row.children, [...parents, row.name], into);
    }
    return into;
}

export function matchesTarget(row: TargetRow, needle: string): boolean {
    return needle === '' || row.name.toLowerCase().includes(needle) || row.children.some((child) => matchesTarget(child, needle));
}
