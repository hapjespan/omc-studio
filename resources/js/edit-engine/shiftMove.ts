import type { AreaKey, AreaKind, DrawnCanvas, ElementKind } from '../layout';
import type { CanvasStructure, LayoutRect, MoveVerdict, RefusalReason } from './DrawingEngine';

export interface Point {
    x: number;
    y: number;
}

export interface MoveBlock {
    id: string;
    kind: ElementKind;
    areaKey: AreaKey;
    areaKind: AreaKind;
    parentId: string | null;
    name: string;
    unknownAllocation: boolean;
    x: number;
    y: number;
    width: number;
    height: number;
    z: number;
}

export type MoveModel = MoveBlock[];

const CONTAINERS = new Set<ElementKind>(['domain', 'cluster']);

const index = (model: MoveModel): Map<string, MoveBlock> => new Map(model.map((block) => [block.id, block]));

const holds = (block: MoveBlock, point: Point): boolean => point.x >= block.x && point.x < block.x + block.width && point.y >= block.y && point.y < block.y + block.height;

function depth(byId: Map<string, MoveBlock>, block: MoveBlock): number {
    let levels = 0;
    let at: MoveBlock | undefined = block;
    while (at?.parentId) {
        at = byId.get(at.parentId);
        if (at) levels++;
    }
    return levels;
}

function descends(byId: Map<string, MoveBlock>, block: MoveBlock, ancestorId: string): boolean {
    for (let at: MoveBlock | undefined = block; at; at = at.parentId ? byId.get(at.parentId) : undefined) {
        if (at.id === ancestorId) return true;
    }
    return false;
}

export function buildMoveModel(canvas: DrawnCanvas, structure: CanvasStructure): MoveModel {
    const kinds = new Map(structure.areas.map((area) => [area.key, area.kind]));
    const elements = new Map(structure.elements.map((element) => [element.id, element]));
    const model: MoveModel = [];
    for (const block of canvas.blocks) {
        const element = elements.get(block.id);
        const areaKind = kinds.get(block.areaKey);
        if (block.kind === 'area' || !element || !areaKind) continue;
        model.push({
            id: block.id,
            kind: element.kind,
            areaKey: block.areaKey,
            areaKind,
            parentId: block.parentId,
            name: element.name,
            unknownAllocation: element.unknownAllocation === true,
            x: block.x,
            y: block.y,
            width: block.width,
            height: block.drawnHeight,
            z: block.z,
        });
    }
    return model;
}

export function innermostAt(model: MoveModel, point: Point, keep: (block: MoveBlock) => boolean = () => true): MoveBlock | null {
    const byId = index(model);
    const under = model.filter((block) => keep(block) && holds(block, point));
    return under.length === 0 ? null : under.reduce((best, block) => (depth(byId, block) > depth(byId, best) ? block : best));
}

export function judge(model: MoveModel, draggedId: string, point: Point): MoveVerdict | null {
    const byId = index(model);
    const dragged = byId.get(draggedId);
    if (!dragged || (dragged.kind !== 'activity' && dragged.kind !== 'cluster')) return null;

    const found = innermostAt(model, point, (block) => CONTAINERS.has(block.kind) && !descends(byId, block, draggedId));
    if (!found) return null;
    const target = dragged.kind === 'cluster' && found.kind === 'cluster' ? (found.parentId ? byId.get(found.parentId) : undefined) : found;
    if (!target || target.id === dragged.parentId) return null;

    const refuse = (reason: RefusalReason): MoveVerdict => ({ valid: false, targetId: target.id, targetName: target.name, reason });
    if (dragged.kind === 'cluster' && target.kind !== 'domain') return refuse('cluster_needs_domain');
    if (dragged.kind === 'cluster' && target.areaKind === 'list') return refuse('no_clusters_in_list');
    if (target.areaKind !== dragged.areaKind) return refuse('other_kind');
    if (target.unknownAllocation) return refuse('unknown_allocation');
    if (model.some((block) => block.parentId === target.id && block.id !== dragged.id && block.name.toLowerCase() === dragged.name.toLowerCase())) return refuse('name_taken');

    return { valid: true, targetId: target.id, targetName: target.name, narrowed: dragged.width > target.width };
}

export function landing(model: MoveModel, draggedId: string, targetId: string, topLeft: Point): LayoutRect {
    const byId = index(model);
    const dragged = byId.get(draggedId) as MoveBlock;
    const target = byId.get(targetId) as MoveBlock;
    const width = Math.min(dragged.width, target.width);
    return {
        x: Math.round(Math.min(Math.max(topLeft.x - target.x, 0), Math.max(target.width - width, 0))),
        y: Math.round(Math.max(topLeft.y - target.y, 0)),
        width: Math.round(width),
        height: Math.round(dragged.height),
        z: dragged.z,
    };
}

export interface ChildBox {
    id: string;
    x: number;
    width: number;
}

export function fitChildren(children: ChildBox[], blockWidth: number, minimum = 40): ChildBox[] {
    return children.map((child) => {
        const x = Math.min(child.x, Math.max(0, blockWidth - child.width));

        return { id: child.id, x, width: Math.max(minimum, Math.min(child.width, blockWidth - x)) };
    });
}
