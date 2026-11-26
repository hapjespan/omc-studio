import { describe, expect, it } from 'vitest';
import type { DrawingCommand, MoveIntent } from '../../../resources/js/edit-engine';
import { createMoveFlow, type MoveFlowDependencies } from '../../../resources/js/pages/Drawing/moveFlow';
import type { MoveResult } from '../../../resources/js/pages/Drawing/moveRequest';
import { MoveConflict, type MoveTask } from '../../../resources/js/pages/Drawing/SaveQueue';

const intent = (extra: Partial<MoveIntent> = {}): MoveIntent => ({ kind: 'move', elementId: 'a', targetId: 'b', layout: { x: 0, y: 0, width: 100, height: 30, z: 0 }, origin: 'drag', narrowed: true, ...extra });

function setup(result: MoveResult, exists = true) {
    const commands: DrawingCommand[] = [];
    const said: string[] = [];
    const refreshed: [boolean, string][] = [];
    const tasks: MoveTask[] = [];
    const reloaded: boolean[] = [];
    const deps: MoveFlowDependencies = {
        canvas: 1,
        apply: (command) => commands.push(command),
        enqueue: (task) => tasks.push(task),
        nameOf: (id) => id.toUpperCase(),
        exists: () => exists,
        valid: () => exists,
        reloadStructure: async () => {
            reloaded.push(true);
        },
        say: (text) => said.push(text),
        text: (key, values) => `${key}${values ? JSON.stringify(values) : ''}`,
        refresh: async (keepOwn, message) => void refreshed.push([keepOwn, message]),
        send: async () => result,
    };
    const flow = createMoveFlow(deps);
    return { flow, commands, said, refreshed, tasks, reloaded, run: () => tasks[0]({ structure: 1, layout: 1 }) };
}

describe('move flow', () => {
    it('keeps nothing stored before the user confirms and reverts on cancel', () => {
        const { flow, commands } = setup({ outcome: 'unreachable' });
        flow.onIntent(intent());
        expect(flow.pending.value).not.toBeNull();
        flow.cancel();
        expect(commands).toEqual([{ kind: 'revertMove', elementId: 'a' }]);
        expect(flow.pending.value).toBeNull();
    });

    it('cancelling an undo intent leaves history untouched through cancelMoveHistory', () => {
        const { flow, commands } = setup({ outcome: 'unreachable' });
        flow.onIntent(intent({ origin: 'undo' }));
        flow.cancel();
        expect(commands).toEqual([{ kind: 'cancelMoveHistory', direction: 'undo' }]);
    });

    it('commits a saved move, announces it with the narrowing and returns the new versions', async () => {
        const { flow, commands, said, tasks, reloaded, run } = setup({ outcome: 'saved', versions: { structure: 2, layout: 4 }, layout: { width: 100 } });
        flow.onIntent(intent());
        flow.confirm();
        expect(tasks).toHaveLength(1);
        expect(await run()).toEqual({ structure: 2, layout: 4 });
        expect(commands).toEqual([{ kind: 'commitMove', elementId: 'a', targetId: 'b', layout: intent().layout, mode: 'new' }]);
        expect(reloaded).toEqual([true]);
        expect(said[0]).toContain('drawing.moved');
        expect(said[0]).toContain('drawing.moved_narrower');
        expect(flow.busy.value).toBe(false);
    });

    it('commits an undo with the undo mode', async () => {
        const { flow, commands, run } = setup({ outcome: 'saved', versions: { structure: 2, layout: 4 }, layout: null });
        flow.onIntent(intent({ origin: 'undo', layout: null, position: 1 }));
        flow.confirm();
        await run();
        expect(commands[0]).toMatchObject({ kind: 'commitMove', mode: 'undo', layout: null });
    });

    it('reverts and says why when the server refuses a drag', async () => {
        const { flow, commands, said, run } = setup({ outcome: 'refused', messages: ['Naam taken'] });
        flow.onIntent(intent());
        flow.confirm();
        expect(await run()).toEqual({ structure: 1, layout: 1 });
        expect(commands).toEqual([{ kind: 'revertMove', elementId: 'a' }]);
        expect(said).toEqual(['Naam taken']);
    });

    it('clears history and reloads when a refused undo comes back', async () => {
        const { flow, commands, refreshed, run } = setup({ outcome: 'refused', messages: ['Naam taken'] });
        flow.onIntent(intent({ origin: 'redo' }));
        flow.confirm();
        await run();
        expect(commands).toEqual([{ kind: 'clearHistory' }]);
        expect(refreshed).toEqual([[false, 'Naam taken']]);
    });

    it('turns a conflict into MoveConflict and asks again after the structure reload when still possible', async () => {
        const { flow, commands, run } = setup({ outcome: 'conflict', conflict: 'structure_changed' });
        flow.onIntent(intent());
        flow.confirm();
        await expect(run()).rejects.toBeInstanceOf(MoveConflict);
        expect(commands[0]).toEqual({ kind: 'revertMove', elementId: 'a' });
        flow.afterStructureRefresh();
        expect(commands.at(-1)).toEqual({ kind: 'previewMove', elementId: 'a', targetId: 'b' });
    });

    it('says the move was not saved when the Element or its target is gone after the reload', async () => {
        const { flow, said, run } = setup({ outcome: 'conflict', conflict: 'structure_changed' }, false);
        flow.onIntent(intent());
        flow.confirm();
        await run().catch(() => undefined);
        flow.afterStructureRefresh();
        expect(said).toEqual(['drawing.move_not_saved']);
    });

    it('reverts and fails the queue task when the server cannot be reached', async () => {
        const { flow, commands, said, run } = setup({ outcome: 'unreachable' });
        flow.onIntent(intent());
        flow.confirm();
        await expect(run()).rejects.toThrow('unreachable');
        expect(commands).toEqual([{ kind: 'revertMove', elementId: 'a' }]);
        expect(said).toEqual(['drawing.move_not_saved']);
    });

    it('does not replay an undo after a structure change: it clears the history and says so', async () => {
        const { flow, commands, said, run } = setup({ outcome: 'conflict', conflict: 'structure_changed' });
        flow.onIntent(intent({ origin: 'undo' }));
        flow.confirm();
        await run().catch(() => undefined);
        flow.afterStructureRefresh();
        expect(commands.at(-1)).toEqual({ kind: 'clearHistory' });
        expect(said).toEqual(['drawing.move_not_saved']);
    });
});
