import { routes } from '../../routes';
import type { SaveResult } from './SaveQueue';
import type { ResetScope } from '../../edit-engine/commands/reset';

const xsrfToken = (): string => decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');

export async function sendReset(canvas: string | number, scope: ResetScope, elementId: string | null, versions: { structure: number; layout: number }): Promise<SaveResult> {
    const response = await fetch(routes.layoutReset(canvas), {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-XSRF-TOKEN': xsrfToken(), Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ scope, ...(elementId === null ? {} : { element_id: elementId }), structure_version: versions.structure, layout_version: versions.layout }),
    }).catch(() => null);
    if (response === null) return { outcome: 'unreachable' };
    const answer = (await response.json().catch(() => ({}))) as { layout_version?: number; conflict?: 'structure_changed' | 'layout_changed'; errors?: { rule?: string[] } };
    if (response.ok && typeof answer.layout_version === 'number') return { outcome: 'saved', layoutVersion: answer.layout_version };
    if (response.status === 409 && answer.conflict !== undefined) return { outcome: 'conflict', conflict: answer.conflict };
    if (response.status === 422) return { outcome: 'refused', messages: answer.errors?.rule ?? [], codes: [], elements: [] };

    return { outcome: 'unreachable' };
}
