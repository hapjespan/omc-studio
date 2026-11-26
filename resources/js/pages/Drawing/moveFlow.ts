import { ref, type Ref } from 'vue';
import type { DrawingCommand, MoveIntent } from '../../edit-engine';
import { sendMove, type MoveSender } from './moveRequest';
import { MoveConflict, type LayoutVersions, type MoveTask } from './SaveQueue';

export interface MoveFlowDependencies {
    canvas: string | number;
    apply: (command: DrawingCommand) => void;
    enqueue: (task: MoveTask) => void;
    nameOf: (id: string) => string;
    exists: (id: string) => boolean;
    valid: (elementId: string, targetId: string) => boolean;
    reloadStructure: () => Promise<void>;
    say: (text: string) => void;
    text: (key: string, values?: Record<string, string>) => string;
    refresh: (keepOwn: boolean, message: string) => Promise<void>;
    send?: MoveSender;
}

export interface MoveFlow {
    pending: Ref<MoveIntent | null>;
    busy: Ref<boolean>;
    onIntent(intent: MoveIntent): void;
    confirm(): void;
    cancel(): void;
    afterStructureRefresh(): void;
    structureReload(silent?: boolean): void;
    hasLost(): boolean;
}

const commitMode = (intent: MoveIntent): 'new' | 'undo' | 'redo' => (intent.origin === 'undo' || intent.origin === 'redo' ? intent.origin : 'new');

export function createMoveFlow(deps: MoveFlowDependencies): MoveFlow {
    const pending = ref<MoveIntent | null>(null);
    const busy = ref(false);
    const send = deps.send ?? sendMove;
    let lost: MoveIntent | null = null;

    const undoing = (intent: MoveIntent): boolean => intent.origin === 'undo' || intent.origin === 'redo';
    const revert = (intent: MoveIntent): void => {
        if (undoing(intent)) deps.apply({ kind: 'cancelMoveHistory', direction: intent.origin as 'undo' | 'redo' });
        else deps.apply({ kind: 'revertMove', elementId: intent.elementId });
    };
    const announce = (intent: MoveIntent): void => {
        const narrowed = intent.narrowed === true;
        const moved = deps.text('drawing.moved', { block: deps.nameOf(intent.elementId), target: deps.nameOf(intent.targetId) });
        deps.say(narrowed ? `${moved}. ${deps.text('drawing.moved_narrower', { block: deps.nameOf(intent.elementId), target: deps.nameOf(intent.targetId) })}` : moved);
    };

    return {
        pending,
        busy,
        onIntent(intent) {
            pending.value = intent;
        },
        confirm() {
            const intent = pending.value;
            if (!intent) return;
            pending.value = null;
            busy.value = true;
            deps.enqueue(async (versions: LayoutVersions) => {
                const result = await send(deps.canvas, versions, intent);
                busy.value = false;
                if (result.outcome === 'saved') {
                    deps.apply({ kind: 'commitMove', elementId: intent.elementId, targetId: intent.targetId, layout: intent.layout, mode: commitMode(intent) });
                    announce(intent);
                    void deps.reloadStructure();
                    return result.versions;
                }
                if (result.outcome === 'conflict') {
                    lost = intent;
                    revert(intent);
                    throw new MoveConflict(result.conflict);
                }
                if (result.outcome === 'refused') {
                    deps.say(result.messages.join(' ') || deps.text('drawing.move_not_saved'));
                    if (undoing(intent)) {
                        deps.apply({ kind: 'clearHistory' });
                        void deps.refresh(false, result.messages.join(' '));
                    } else revert(intent);
                    return versions;
                }
                revert(intent);
                deps.say(deps.text('drawing.move_not_saved'));
                throw new Error('unreachable');
            });
        },
        cancel() {
            const intent = pending.value;
            pending.value = null;
            if (intent) revert(intent);
        },
        afterStructureRefresh() {
            const intent = lost;
            lost = null;
            if (!intent) return;
            if (undoing(intent)) {
                deps.apply({ kind: 'clearHistory' });
                deps.say(deps.text('drawing.move_not_saved'));
                return;
            }
            if (deps.exists(intent.elementId) && deps.exists(intent.targetId) && deps.valid(intent.elementId, intent.targetId)) deps.apply({ kind: 'previewMove', elementId: intent.elementId, targetId: intent.targetId });
            else deps.say(deps.text('drawing.move_not_saved'));
        },
        hasLost() {
            return lost !== null;
        },
        structureReload(silent = false) {
            if (lost && !silent) deps.say(deps.text('drawing.move_not_saved'));
            lost = null;
        },
    };
}
