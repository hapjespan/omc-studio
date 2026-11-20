import { TOOL_SHORTCUTS, type Editor, type Tool } from '@open-pencil/core/editor';
import type { SceneGraph, SceneNode } from '@open-pencil/scene-graph';
import { TEXT_SUFFIX } from './scene';

export interface Refusal {
    action: string;
    id: string;
}

export interface Restrictions {
    refusals: Refusal[];
    privileged<T>(action: () => T): T;
    release(): void;
}

type Box = Pick<SceneNode, 'x' | 'y' | 'width' | 'height'>;
type Method = (...args: never[]) => unknown;

const ALLOWED_TOOLS = new Set<Tool>(['SELECT', 'HAND']);
const KEYS_WITHOUT_EFFECT = ['Delete', 'Backspace', 'Control+KeyD', 'Control+KeyC', 'Control+KeyV', 'Control+KeyG', 'Control+Alt+KeyK', 'Control+KeyX'];

export const inertShortcuts = [...Object.keys(TOOL_SHORTCUTS).filter((key) => !ALLOWED_TOOLS.has(TOOL_SHORTCUTS[key as keyof typeof TOOL_SHORTCUTS] as Tool)), ...KEYS_WITHOUT_EFFECT];

export function clampToParent(node: Box, parent: Pick<SceneNode, 'width' | 'height'>, changes: Partial<SceneNode>): Partial<SceneNode> {
    const next = { ...changes };
    const touchesBox = 'x' in changes || 'y' in changes || 'width' in changes || 'height' in changes;
    if (!touchesBox) return next;
    const width = Math.min(changes.width ?? node.width, parent.width);
    const height = Math.min(changes.height ?? node.height, parent.height);
    if ('width' in changes) next.width = width;
    if ('height' in changes) next.height = height;
    if ('x' in changes || 'width' in changes) next.x = Math.min(Math.max(changes.x ?? node.x, 0), parent.width - width);
    if ('y' in changes || 'height' in changes) next.y = Math.min(Math.max(changes.y ?? node.y, 0), parent.height - height);
    return next;
}

export function restrict(editor: Editor, graph: SceneGraph, lockedIds: string[], onText: () => void = () => undefined, frozenIds: string[] = []): Restrictions {
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
    const guardedUpdate = (name: 'updateNode' | 'updateNodePreview') =>
        ((id: string, changes: Partial<SceneNode>) => {
            const node = graph.getNode(id);
            const parent = parentOf(id);
            if (!node || locked.has(id)) return refuse(name, id);
            const safe = parent && parent.type !== 'CANVAS' ? clampToParent(node, parent, changes) : changes;
            return original(name).call(graph, id, safe);
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
        const parent = parentOf(id);
        if (!node || locked.has(id)) return refuse('updateNodePositionPreview', id);
        const safe = parent ? clampToParent(node, parent, { x, y }) : { x, y };
        return original('updateNodePositionPreview').call(graph, id, safe.x ?? x, safe.y ?? y);
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
