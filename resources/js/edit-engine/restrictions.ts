import { type Editor, type Tool } from '@open-pencil/core/editor';
import type { SceneGraph, SceneNode } from '@open-pencil/scene-graph';
import { snapChanges } from './commands/grid';
import { constrain, type Box as ConstraintBox } from './constraints';
import { ARTBOARD_ID, TEXT_SUFFIX } from './scene';

export interface Refusal {
    action: string;
    id: string;
}

export interface Restrictions {
    refusals: Refusal[];
    privileged<T>(action: () => T): T;
    release(): void;
}

type Method = (...args: never[]) => unknown;

const ALLOWED_TOOLS = new Set<Tool>(['SELECT', 'HAND']);

export interface RestrictionHooks {
    onPushBack?: (id: string) => void;
    onResize?: (id: string) => void;
    snap?: () => boolean;
}

const BOX_KEYS = ['x', 'y', 'width', 'height'] as const;

export function restrict(editor: Editor, graph: SceneGraph, lockedIds: string[], onText: () => void = () => undefined, frozenIds: string[] = [], hooks: RestrictionHooks = {}): Restrictions {
    const refusals: Refusal[] = [];
    const originals = new Map<string, Method>();
    const target = graph as unknown as Record<string, Method>;
    const locked = new Set(lockedIds);
    for (const node of graph.getAllNodes()) {
        if (node.id.endsWith(TEXT_SUFFIX)) locked.add(node.id);
    }
    for (const id of locked) graph.updateNode(id, { locked: true });
    for (const id of frozenIds) locked.add(id);

    const patch = (name: string, replacement: Method) => {
        originals.set(name, target[name]);
        target[name] = replacement;
    };
    const original = (name: string) => originals.get(name) as (...args: unknown[]) => unknown;
    const refuse = (action: string, id: string) => {
        refusals.push({ action, id });
    };
    const parentOf = (id: string) => {
        const node = graph.getNode(id);
        return node?.parentId ? graph.getNode(node.parentId) : undefined;
    };
    const childRight = (node: SceneNode): number =>
        Math.max(0, ...node.childIds.filter((id) => !id.endsWith(TEXT_SUFFIX)).map((id) => graph.getNode(id)).map((child) => (child ? child.x + child.width : 0)));
    const grow = (id: string, height: number): void => {
        const node = graph.getNode(id);
        if (!node || node.height >= height) return;
        original('updateNode').call(graph, id, { height });
        const parent = parentOf(id);
        if (parent && parent.id !== ARTBOARD_ID && parent.type !== 'CANVAS') grow(parent.id, node.y + height);
    };
    const guardedUpdate = (name: 'updateNode' | 'updateNodePreview') =>
        ((id: string, changes: Partial<SceneNode>) => {
            const node = graph.getNode(id);
            const parent = parentOf(id);
            if (!node || locked.has(id)) return refuse(name, id);
            if (!parent || parent.type === 'CANVAS') return original(name).call(graph, id, changes);
            const container = parent.id === ARTBOARD_ID ? ({ kind: 'artboard-area' } as const) : ({ kind: 'parent', width: parent.width, height: parent.height } as const);
            const asked: Partial<ConstraintBox> = {};
            for (const key of BOX_KEYS) if (key in changes) asked[key] = changes[key] as number;
            const result = constrain(node, name === 'updateNodePreview' && hooks.snap?.() ? snapChanges(asked) : asked, container, childRight(node));
            if (result.pushed) hooks.onPushBack?.(id);
            if (changes.height !== undefined && changes.height !== node.height) hooks.onResize?.(id);
            const done = original(name).call(graph, id, { ...changes, ...result.changes });
            if (result.growParentTo !== null) grow(parent.id, result.growParentTo);
            return done;
        }) as unknown as Method;

    patch('createNode', ((_type: string, parentId: string) => {
        refuse('create', parentId);
        throw new Error('creating nodes is not allowed');
    }) as unknown as Method);
    patch('createNodeWithId', ((id: string) => {
        refuse('create', id);
        throw new Error('creating nodes is not allowed');
    }) as unknown as Method);
    patch('cloneTree', ((id: string) => {
        refuse('create', id);
        return null;
    }) as unknown as Method);
    patch('createInstance', ((id: string) => {
        refuse('create', id);
        return null;
    }) as unknown as Method);
    patch('deleteNode', ((id: string) => refuse('delete', id)) as unknown as Method);
    patch('reparentNode', ((id: string) => refuse('reparent', id)) as unknown as Method);
    patch('insertChildAt', ((id: string, parentId: string, index: number) => {
        if (graph.getNode(id)?.parentId !== parentId) return refuse('reparent', id);
        return original('insertChildAt').call(graph, id, parentId, index);
    }) as unknown as Method);
    patch('reorderChild', ((id: string, parentId: string, index: number) => {
        if (graph.getNode(id)?.parentId !== parentId) return refuse('reparent', id);
        return original('reorderChild').call(graph, id, parentId, index);
    }) as unknown as Method);
    patch('updateNode', guardedUpdate('updateNode'));
    patch('updateNodePreview', guardedUpdate('updateNodePreview'));
    patch('updateNodePositionPreview', ((id: string, x: number, y: number) => {
        const node = graph.getNode(id);
        if (!node || locked.has(id)) return refuse('updateNodePositionPreview', id);
        return (target.updateNodePreview as unknown as (id: string, changes: Partial<SceneNode>) => unknown).call(graph, id, { x, y });
    }) as unknown as Method);

    const setTool = editor.setTool.bind(editor);
    const startTextEditing = editor.startTextEditing.bind(editor);
    editor.setTool = (tool: Tool) => {
        if (ALLOWED_TOOLS.has(tool)) setTool(tool);
        else refuse('tool', String(tool));
    };
    editor.startTextEditing = (id: string) => {
        refuse('text', id);
        onText();
    };

    return {
        refusals,
        privileged<T>(action: () => T): T {
            const guarded = new Map<string, Method>();
            for (const [name, fn] of originals) {
                guarded.set(name, target[name]);
                target[name] = fn;
            }
            try {
                return action();
            } finally {
                for (const [name, fn] of guarded) target[name] = fn;
            }
        },
        release() {
            for (const [name, fn] of originals) target[name] = fn;
            editor.setTool = setTool;
            editor.startTextEditing = startTextEditing;
        },
    };
}
