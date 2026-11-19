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
}

export function indexTargets(rows: TargetRow[], parents: string[] = [], into: Record<string, TargetInfo> = {}): Record<string, TargetInfo> {
    for (const row of rows) {
        into[String(row.id)] = { id: String(row.id), name: row.name, kind: row.kind, path: [...parents, row.name].join(' > ') };
        indexTargets(row.children, [...parents, row.name], into);
    }
    return into;
}
