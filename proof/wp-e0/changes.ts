import type { SceneGraph } from '@open-pencil/scene-graph';
import type { ReadBlock } from './roundtrip';

export interface FinishedAction {
    ids: string[];
    at: number;
}

interface NoticeSource {
    onEditorEvent(event: 'history:changed', handler: () => void): () => void;
}

export function watchFinishedActions(editor: NoticeSource, graph: SceneGraph, listener: (action: FinishedAction) => void): () => void {
    const touched = new Set<string>();
    const stop = graph.onNodeEvents({
        updated: (id) => touched.add(id),
        created: (node) => touched.add(node.id),
        deleted: (id) => touched.add(id),
        reparented: (id) => touched.add(id),
        reordered: (id) => touched.add(id),
    });
    const stopHistory = editor.onEditorEvent('history:changed', () => {
        if (touched.size === 0) return;
        listener({ ids: [...touched], at: performance.now() });
        touched.clear();
    });
    return () => {
        stop();
        stopHistory();
    };
}

export function changedIds(before: ReadBlock[], after: ReadBlock[]): string[] {
    const previous = new Map(before.map((block) => [block.id, JSON.stringify(block)]));
    const ids = after.filter((block) => previous.get(block.id) !== JSON.stringify(block)).map((block) => block.id);
    const present = new Set(after.map((block) => block.id));
    return [...ids, ...before.filter((block) => !present.has(block.id)).map((block) => block.id)];
}
