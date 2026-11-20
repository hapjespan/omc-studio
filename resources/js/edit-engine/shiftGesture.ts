import type { Editor } from '@open-pencil/core/editor';
import type { SceneGraph } from '@open-pencil/scene-graph';
import type { MoveVerdict, PlacedMove, RefusalReason, ShiftVerdictEvent } from './DrawingEngine';
import type { Restrictions } from './restrictions';
import { ARTBOARD_ID, TEXT_SUFFIX } from './scene';
import { innermostAt, judge, landing, type MoveModel, type Point } from './shiftMove';

export interface SavedPlace {
    id: string;
    parentId: string;
    x: number;
    y: number;
    width: number;
    height: number;
    index: number;
}

export class MovePlacement {
    private saved: SavedPlace | null = null;

    constructor(
        private readonly graph: SceneGraph,
        private readonly restrictions: Restrictions,
        private readonly base: MoveModel,
    ) {}

    model(): MoveModel {
        return this.base.map((block) => {
            const node = this.graph.getNode(block.id);
            if (!node) return block;
            const bounds = this.graph.getAbsoluteBounds(block.id);
            const parentId = node.parentId;
            return { ...block, parentId: !parentId || parentId === ARTBOARD_ID || parentId === block.areaKey ? null : parentId, x: bounds.x, y: bounds.y, width: node.width, height: node.height };
        });
    }

    former(): SavedPlace | null {
        return this.saved;
    }

    capture(elementId: string): SavedPlace | null {
        const node = this.graph.getNode(elementId);
        const parent = node?.parentId ? this.graph.getNode(node.parentId) : null;
        if (!node || !parent) return null;
        return { id: elementId, parentId: parent.id, x: node.x, y: node.y, width: node.width, height: node.height, index: parent.childIds.indexOf(elementId) };
    }

    restore(place: SavedPlace): void {
        this.restrictions.privileged(() => {
            this.graph.reparentNode(place.id, place.parentId);
            this.graph.updateNode(place.id, { x: place.x, y: place.y, width: place.width, height: place.height });
            this.graph.reorderChild(place.id, place.parentId, place.index);
        });
    }

    previewMove(elementId: string, targetId: string, topLeft?: Point): PlacedMove | null {
        const model = this.model();
        const target = model.find((block) => block.id === targetId);
        const place = this.capture(elementId);
        if (!target || !place || !model.some((block) => block.id === elementId)) return null;
        const layout = landing(model, elementId, targetId, topLeft ?? { x: target.x, y: target.y + this.lowestChild(targetId) });
        this.saved = place;
        this.restrictions.privileged(() => {
            this.graph.reparentNode(elementId, targetId);
            this.graph.updateNode(elementId, { x: layout.x, y: layout.y, width: layout.width, height: layout.height });
        });
        return { kind: 'move', elementId, targetId, layout };
    }

    revert(elementId: string): void {
        const place = this.saved;
        if (!place || place.id !== elementId) return;
        this.saved = null;
        this.restore(place);
    }

    private lowestChild(targetId: string): number {
        const node = this.graph.getNode(targetId);
        const children = (node?.childIds ?? []).filter((id) => !id.endsWith(TEXT_SUFFIX)).map((id) => this.graph.getNode(id));
        return Math.max(0, ...children.map((child) => (child ? child.y + child.height : 0)));
    }
}

export interface ShiftMoveOptions {
    editor: Editor;
    graph: SceneGraph;
    restrictions: Restrictions;
    canvas: HTMLCanvasElement;
    model: MoveModel;
    onIntent: (move: PlacedMove) => void;
    onRefusal?: (reason: RefusalReason, targetName: string) => void;
}

export interface ShiftMoveHandle {
    placement: MovePlacement;
    onVerdict(listener: (event: ShiftVerdictEvent | null) => void): () => void;
    detach(): void;
}

interface Gesture {
    id: string;
    grab: Point;
    moved: boolean;
    place: SavedPlace;
}

export function attachShiftMove(options: ShiftMoveOptions): ShiftMoveHandle {
    const { editor, canvas, onIntent, onRefusal } = options;
    const placement = new MovePlacement(options.graph, options.restrictions, options.model);
    const listeners = new Set<(event: ShiftVerdictEvent | null) => void>();
    const forwarded = new WeakSet<Event>();
    const setDropTarget = editor.setDropTarget.bind(editor);
    let snapshot: MoveModel = options.model;
    let origin: Point | null = null;
    let gesture: Gesture | null = null;
    let verdict: MoveVerdict | null = null;
    editor.setDropTarget = () => undefined;

    const at = (event: MouseEvent): Point => {
        const rect = canvas.getBoundingClientRect();
        return editor.screenToCanvas(event.clientX - rect.left, event.clientY - rect.top);
    };

    const tell = (event: ShiftVerdictEvent | null): void => {
        for (const listener of listeners) listener(event);
    };

    const begin = (): void => {
        if (!origin) return;
        const block = innermostAt(snapshot, origin);
        const place = block ? placement.capture(block.id) : null;
        if (!block || !place) return;
        gesture = { id: block.id, grab: { x: origin.x - block.x, y: origin.y - block.y }, moved: false, place };
    };

    const down = (event: MouseEvent): void => {
        gesture = null;
        snapshot = placement.model();
        origin = at(event);
    };

    const move = (event: MouseEvent): void => {
        if (forwarded.has(event) || !(event.buttons & 1)) return;
        if (!gesture && origin) begin();
        origin = null;
        if (!gesture) return;
        gesture.moved = true;
        if (!event.shiftKey) {
            verdict = null;
            return tell(null);
        }
        verdict = judge(snapshot, gesture.id, at(event));
        tell(verdict ? { verdict, x: event.clientX, y: event.clientY } : null);
        event.stopImmediatePropagation();
        const copy = new MouseEvent('mousemove', {
            bubbles: true,
            cancelable: true,
            composed: true,
            clientX: event.clientX,
            clientY: event.clientY,
            screenX: event.screenX,
            screenY: event.screenY,
            button: event.button,
            buttons: event.buttons,
            ctrlKey: event.ctrlKey,
            altKey: event.altKey,
            metaKey: event.metaKey,
            shiftKey: false,
        });
        forwarded.add(copy);
        (event.target ?? canvas).dispatchEvent(copy);
    };

    const up = (event: MouseEvent): void => {
        const current = gesture;
        const decided = verdict;
        gesture = null;
        origin = null;
        verdict = null;
        tell(null);
        if (!current || !current.moved || !event.shiftKey || !decided) return;
        event.stopImmediatePropagation();
        window.dispatchEvent(new Event('blur'));
        if (!decided.valid) {
            placement.restore(current.place);
            onRefusal?.(decided.reason, decided.targetName);
            return;
        }
        const released = at(event);
        const intent = placement.previewMove(current.id, decided.targetId, { x: released.x - current.grab.x, y: released.y - current.grab.y });
        if (intent) onIntent(intent);
    };

    const handlers: [string, (event: MouseEvent) => void][] = [
        ['mousedown', down],
        ['mousemove', move],
        ['pointerup', up],
    ];
    for (const [name, handler] of handlers) window.addEventListener(name, handler as EventListener, { capture: true });

    return {
        placement,
        onVerdict(listener: (event: ShiftVerdictEvent | null) => void): () => void {
            listeners.add(listener);
            return () => listeners.delete(listener);
        },
        detach: () => {
            for (const [name, handler] of handlers) window.removeEventListener(name, handler as EventListener, { capture: true });
            editor.setDropTarget = setDropTarget;
            listeners.clear();
        },
    };
}
