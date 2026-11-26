import type { SceneGraph } from '@open-pencil/scene-graph';
import { absolutePosition, type Block } from './layout';
import type { Restrictions } from './restrict';

export interface Placed {
    id: string;
    kind: Block['kind'];
    parent: string | null;
    ax: number;
    ay: number;
    width: number;
    height: number;
    area?: string;
    name: string;
}

export interface Verdict {
    targetId: string;
    valid: boolean;
    label: string;
}

export interface MoveIntent {
    kind: 'move';
    elementId: string;
    targetId: string;
    layout: { x: number; y: number; width: number; height: number };
}

const CONTAINERS = new Set(['domain', 'cluster']);

export function placeBlocks(blocks: Block[]): Placed[] {
    return blocks.map((block) => {
        const { x, y } = absolutePosition(blocks, block.id);
        return { id: block.id, kind: block.kind, parent: block.parent, ax: x, ay: y, width: block.width, height: block.height, area: block.area, name: block.name };
    });
}

const contains = (block: Placed, point: { x: number; y: number }) => point.x >= block.ax && point.x < block.ax + block.width && point.y >= block.ay && point.y < block.ay + block.height;

function depth(placed: Map<string, Placed>, block: Placed) {
    let levels = 0;
    for (let at = block; at.parent; at = placed.get(at.parent)!) levels++;
    return levels;
}

function descends(placed: Map<string, Placed>, block: Placed, ancestorId: string) {
    for (let at: Placed | undefined = block; at; at = at.parent ? placed.get(at.parent) : undefined) {
        if (at.id === ancestorId) return true;
    }
    return false;
}

function rootDomain(placed: Map<string, Placed>, block: Placed) {
    let at = block;
    while (at.kind !== 'domain' && at.parent) at = placed.get(at.parent)!;
    return at;
}

export function judge(blocks: Placed[], draggedId: string, point: { x: number; y: number }): Verdict | null {
    const placed = new Map(blocks.map((block) => [block.id, block]));
    const dragged = placed.get(draggedId);
    if (!dragged || (dragged.kind !== 'activity' && dragged.kind !== 'cluster')) return null;
    const under = blocks.filter((block) => CONTAINERS.has(block.kind) && !descends(placed, block, draggedId) && contains(block, point));
    if (under.length === 0) return null;
    let target = under.reduce((best, block) => (depth(placed, block) > depth(placed, best) ? block : best));
    if (dragged.kind === 'cluster' && target.kind === 'cluster') target = placed.get(target.parent!)!;
    if (target.id === dragged.parent) return null;
    const refuse = (reason: string): Verdict => ({ targetId: target.id, valid: false, label: `Kan hier niet: ${reason}` });
    if (dragged.kind === 'cluster' && target.kind !== 'domain') return refuse('een Cluster hoort onder een Domain');
    if (rootDomain(placed, target).area !== rootDomain(placed, dragged).area) return refuse('ander soort gebied');
    const sibling = blocks.find((block) => block.parent === target.id && block.id !== dragged.id && block.name.toLowerCase() === dragged.name.toLowerCase());
    if (sibling) return refuse('de naam bestaat daar al');
    return { targetId: target.id, valid: true, label: `Naar ${target.name}${dragged.width > target.width ? ' (wordt smaller)' : ''}` };
}

export function landing(blocks: Placed[], draggedId: string, targetId: string, topLeft: { x: number; y: number }) {
    const dragged = blocks.find((block) => block.id === draggedId)!;
    const target = blocks.find((block) => block.id === targetId)!;
    const width = Math.min(dragged.width, target.width);
    const clamp = (value: number, max: number) => Math.min(Math.max(value, 0), Math.max(max, 0));
    return {
        x: clamp(topLeft.x - target.ax, target.width - width),
        y: clamp(topLeft.y - target.ay, target.height - dragged.height),
        width,
        height: dragged.height,
    };
}

interface Options {
    editor: any;
    graph: SceneGraph;
    restrictions: Restrictions;
    blocks: Block[];
    canvas: HTMLCanvasElement;
    label: HTMLElement;
    onIntent(intent: MoveIntent): void;
}

export function attachShiftMove(options: Options) {
    const { editor, graph, restrictions, canvas, label, onIntent } = options;
    const livePlaced = (): Placed[] =>
        options.blocks.map((block) => {
            const node = graph.getNode(block.id)!;
            const bounds = graph.getAbsoluteBounds(block.id);
            return { id: block.id, kind: block.kind, parent: node.parentId === 'artboard' ? null : node.parentId, ax: bounds.x, ay: bounds.y, width: node.width, height: node.height, area: block.area, name: block.name };
        });
    let placed = livePlaced();
    const forwarded = new WeakSet<Event>();
    type Saved = { id: string; parent: string; x: number; y: number; width: number; height: number; index: number };
    let gesture: { id: string; grab: { x: number; y: number }; moved: boolean; original: Saved } | null = null;
    let verdict: Verdict | null = null;
    let pending: Saved | null = null;
    const setDropTarget = editor.setDropTarget?.bind(editor);
    editor.setDropTarget = () => undefined;

    const point = (event: MouseEvent) => {
        const rect = canvas.getBoundingClientRect();
        return editor.screenToCanvas(event.clientX - rect.left, event.clientY - rect.top) as { x: number; y: number };
    };
    const showLabel = (event: MouseEvent | null) => {
        label.hidden = event === null || verdict === null;
        if (!event || !verdict) return;
        label.textContent = verdict.label;
        label.dataset.valid = String(verdict.valid);
        label.style.left = `${event.clientX + 16}px`;
        label.style.top = `${event.clientY + 16}px`;
    };

    let origin: { x: number; y: number } | null = null;
    const down = (event: MouseEvent) => {
        gesture = null;
        placed = livePlaced();
        origin = point(event);
    };

    const begin = () => {
        const selected: any[] = editor.getSelectedNodes();
        if (!origin || selected.length !== 1) return;
        const node = placed.find((block) => block.id === selected[0].id);
        if (!node || origin.x < node.ax || origin.x >= node.ax + node.width || origin.y < node.ay || origin.y >= node.ay + node.height) return;
        const live = graph.getNode(node.id)!;
        const parent = graph.getNode(live.parentId!)!;
        const original = { id: node.id, parent: parent.id, x: live.x, y: live.y, width: live.width, height: live.height, index: parent.childIds.indexOf(node.id) };
        gesture = { id: node.id, grab: { x: origin.x - node.ax, y: origin.y - node.ay }, moved: false, original };
    };

    const move = (event: MouseEvent) => {
        if (forwarded.has(event) || !(event.buttons & 1)) return;
        if (!gesture && origin) begin();
        origin = null;
        if (!gesture) return;
        gesture.moved = true;
        if (!event.shiftKey) {
            verdict = null;
            showLabel(null);
            return;
        }
        verdict = judge(placed, gesture.id, point(event));
        showLabel(event);
        event.stopImmediatePropagation();
        const init: MouseEventInit = {
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
        };
        const copy = new MouseEvent('mousemove', init);
        forwarded.add(copy);
        (event.target ?? canvas).dispatchEvent(copy);
    };

    const up = (event: MouseEvent) => {
        const current = gesture;
        gesture = null;
        origin = null;
        showLabel(null);
        const decided = verdict;
        verdict = null;
        if (!current || !current.moved || !event.shiftKey || !decided || !decided.valid) return;
        event.stopImmediatePropagation();
        window.dispatchEvent(new Event('blur'));
        const p = point(event);
        const block = placed.find((entry) => entry.id === current.id)!;
        const layout = landing(placed, current.id, decided.targetId, { x: p.x - current.grab.x, y: p.y - current.grab.y });
        const intent: MoveIntent = { kind: 'move', elementId: current.id, targetId: decided.targetId, layout };
        pending = current.original;
        restrictions.privileged(() => {
            graph.reparentNode(block.id, decided.targetId);
            graph.updateNode(block.id, layout);
        });
        onIntent(intent);
    };

    const handlers: [string, (event: MouseEvent) => void][] = [['mousedown', down], ['mousemove', move], ['pointerup', up as (event: MouseEvent) => void]];
    for (const [name, handler] of handlers) window.addEventListener(name, handler as EventListener, { capture: true });

    return {
        revertMove(elementId: string) {
            if (!pending || pending.id !== elementId) return;
            const saved = pending;
            pending = null;
            restrictions.privileged(() => {
                graph.reparentNode(saved.id, saved.parent);
                graph.updateNode(saved.id, { x: saved.x, y: saved.y, width: saved.width, height: saved.height });
                graph.reorderChild(saved.id, saved.parent, saved.index);
            });
        },
        detach() {
            for (const [name, handler] of handlers) window.removeEventListener(name, handler as EventListener, { capture: true });
            editor.setDropTarget = setDropTarget;
        },
    };
}
