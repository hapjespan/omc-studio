import type { Editor } from '@open-pencil/core/editor';

export function keepSelectable(editor: Editor, lockedIds: string[]): () => void {
    const locked = new Set(lockedIds);
    const original = editor.select.bind(editor);
    const filtered = (ids: string[]) => ids.filter((id) => !locked.has(id) && !id.endsWith('#text'));
    editor.select = (ids: string[], additive?: boolean) => original(filtered(ids), additive);
    const stop = editor.onEditorEvent('selection:changed', (ids) => {
        const allowed = filtered(ids);
        if (allowed.length !== ids.length) original(allowed);
    });
    return () => {
        stop();
        editor.select = original;
    };
}
