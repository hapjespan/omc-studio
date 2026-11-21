import type { MoveIntent } from '../../edit-engine';
import { routes } from '../../routes';
import type { LayoutVersions } from './SaveQueue';
import { xsrfToken } from '../../xsrf';

export type MoveResult =
    | { outcome: 'saved'; versions: LayoutVersions; layout: { width: number } | null }
    | { outcome: 'conflict'; conflict: 'structure_changed' | 'layout_changed' }
    | { outcome: 'refused'; messages: string[] }
    | { outcome: 'unreachable' };

export type MoveSender = (canvas: string | number, versions: LayoutVersions, intent: MoveIntent) => Promise<MoveResult>;

interface MoveAnswer {
    structure_version?: number;
    layout_version?: number;
    layout?: { width: number } | null;
    conflict?: 'structure_changed' | 'layout_changed';
    errors?: { rule?: string[] };
}


export const sendMove: MoveSender = async (canvas, versions, intent) => {
    const response = await fetch(routes.drawingMoves(canvas), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({
            element_id: intent.elementId,
            target_id: intent.targetId,
            structure_version: versions.structure,
            layout_version: versions.layout,
            layout: intent.layout,
            ...(intent.position === undefined ? {} : { position: intent.position }),
        }),
    }).catch(() => null);

    if (response === null) return { outcome: 'unreachable' };

    const answer = (await response.json().catch(() => ({}))) as MoveAnswer;

    if (response.ok && typeof answer.structure_version === 'number' && typeof answer.layout_version === 'number') {
        return { outcome: 'saved', versions: { structure: answer.structure_version, layout: answer.layout_version }, layout: answer.layout ?? null };
    }
    if (response.status === 409 && answer.conflict !== undefined) return { outcome: 'conflict', conflict: answer.conflict };
    if (response.status === 422) return { outcome: 'refused', messages: answer.errors?.rule ?? [] };

    return { outcome: 'unreachable' };
};
