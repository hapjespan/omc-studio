import { createRenderer, defineComponent, h } from 'vue';
import { describe, expect, it } from 'vitest';
import { shellLayout, useHeaderSlot, useQuietStatus, useWideMain } from '../../../resources/js/shell/layout';

type Node = { children: Node[]; parent: Node | null; text?: string };

const { createApp } = createRenderer<Node, Node>({
    patchProp: () => {},
    insert: (child, parent, anchor) => {
        child.parent = parent;
        parent.children.splice(anchor ? parent.children.indexOf(anchor) : parent.children.length, 0, child);
    },
    remove: (child) => {
        child.parent?.children.splice(child.parent.children.indexOf(child), 1);
    },
    createElement: () => ({ children: [], parent: null }),
    createText: (text) => ({ children: [], parent: null, text }),
    createComment: () => ({ children: [], parent: null }),
    setText: () => {},
    setElementText: () => {},
    parentNode: (node) => node.parent,
    nextSibling: (node) => node.parent?.children[node.parent.children.indexOf(node) + 1] ?? null,
});

function mount(component: object): () => void {
    const app = createApp(component);
    app.mount({ children: [], parent: null });

    return () => app.unmount();
}

describe('the shell layout options', () => {
    it('widens the main padding while a page calls useWideMain and resets it on unmount', () => {
        const unmount = mount(defineComponent({ setup: () => (useWideMain(), () => h('div')) }));
        expect(shellLayout.widePadding).toBe(true);

        unmount();
        expect(shellLayout.widePadding).toBe(false);
    });

    it('quietens the status region while a page calls useQuietStatus and resets it on unmount', () => {
        const unmount = mount(defineComponent({ setup: () => (useQuietStatus(), () => h('div')) }));
        expect(shellLayout.quietStatus).toBe(true);

        unmount();
        expect(shellLayout.quietStatus).toBe(false);
    });

    it('counts a header slot while it is mounted', () => {
        const unmount = mount(defineComponent({ setup: () => (useHeaderSlot(), () => h('div')) }));
        expect(shellLayout.headerSlots).toBe(1);

        unmount();
        expect(shellLayout.headerSlots).toBe(0);
    });
});
