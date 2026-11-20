import type { Editor } from '@open-pencil/core/editor';
import type { LayoutRect, MoveCommitMode, MoveIntent, PlacedMove } from './DrawingEngine';
import type { MovePlacement, SavedPlace } from './shiftGesture';

const MOVE_LABEL = 'Move';

interface MoveStep {
    place: SavedPlace;
    targetId: string;
    layout: LayoutRect | null;
}

export class MoveHistory {
    private started: MoveStep | null = null;
    private pending: { step: MoveStep; direction: 'undo' | 'redo' } | null = null;
    private silent = false;

    constructor(
        private readonly editor: Editor,
        private readonly placement: MovePlacement,
        private readonly layoutOf: (elementId: string) => LayoutRect | null,
        private readonly emit: (intent: MoveIntent) => void,
    ) {}

    dragged(move: PlacedMove): void {
        const place = this.placement.former();
        if (!place || place.id !== move.elementId) return;
        const layout = this.formerLayout(place);
        this.started = { place, targetId: place.parentId, layout };
        this.emit({ ...move, layout: this.styled(move.layout, layout), origin: 'drag' });
    }

    previewMove(elementId: string, targetId: string): void {
        const layout = this.layoutOf(elementId);
        const move = this.placement.previewMove(elementId, targetId);
        const place = this.placement.former();
        if (!move || !place || place.id !== elementId) return;
        this.started = { place, targetId: place.parentId, layout };
        this.emit({ ...move, layout: this.styled(move.layout, layout), origin: 'panel' });
    }

    revertMove(elementId: string): void {
        this.started = null;
        this.placement.revert(elementId);
    }

    commitMove(elementId: string, targetId: string, layout: LayoutRect | null, mode: MoveCommitMode): void {
        if (mode === 'new') return this.record(elementId, targetId, layout);
        const pending = this.pending;
        this.pending = null;
        if (!pending || pending.direction !== mode || pending.step.place.id !== elementId) return;
        this.restore(pending.step.place, layout);
    }

    cancel(direction: 'undo' | 'redo'): void {
        if (this.pending?.direction !== direction) return;
        this.pending = null;
        this.silent = true;
        try {
            if (direction === 'undo') this.editor.undo.redo();
            else this.editor.undo.undo();
        } finally {
            this.silent = false;
        }
    }

    clear(): void {
        this.started = null;
        this.pending = null;
        this.editor.undo.clear();
    }

    private record(elementId: string, targetId: string, layout: LayoutRect | null): void {
        const before = this.started;
        const place = this.placement.capture(elementId);
        this.started = null;
        if (!before || before.place.id !== elementId || !place) return;
        const after: MoveStep = { place, targetId, layout };
        this.editor.undo.push({
            label: MOVE_LABEL,
            forward: () => this.announce(after, 'redo'),
            inverse: () => this.announce(before, 'undo'),
        });
    }

    private announce(step: MoveStep, direction: 'undo' | 'redo'): void {
        if (this.silent) return;
        this.pending = { step, direction };
        this.emit({
            kind: 'move',
            elementId: step.place.id,
            targetId: step.targetId,
            layout: step.layout,
            ...(direction === 'undo' ? { position: step.place.position } : {}),
            origin: direction,
        });
    }

    private restore(place: SavedPlace, layout: LayoutRect | null): void {
        this.placement.restore(layout ? { ...place, x: layout.x, y: layout.y, width: layout.width, height: layout.height } : place);
    }

    private styled(layout: LayoutRect, former: LayoutRect | null): LayoutRect {
        return former?.style ? { ...layout, style: former.style } : layout;
    }

    private formerLayout(place: SavedPlace): LayoutRect | null {
        const here = this.placement.capture(place.id);
        if (!here) return this.layoutOf(place.id);
        this.placement.restore(place);
        const layout = this.layoutOf(place.id);
        this.placement.restore(here);
        return layout;
    }
}
