import { createRenderer, defineComponent, h, nextTick, type ShallowRef } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { TextMeasurer } from '../../../resources/js/layout';

const create = vi.hoisted(() => vi.fn());
vi.mock('../../../resources/js/layout/browserMeasurer', () => ({ createBrowserMeasurer: create }));

type Node = { children: Node[]; parent: Node | null };

const { createApp } = createRenderer<Node, Node>({
    patchProp: () => {},
    insert: (child, parent) => {
        child.parent = parent;
        parent.children.push(child);
    },
    remove: () => {},
    createElement: () => ({ children: [], parent: null }),
    createText: () => ({ children: [], parent: null }),
    createComment: () => ({ children: [], parent: null }),
    setText: () => {},
    setElementText: () => {},
    parentNode: (node) => node.parent,
    nextSibling: () => null,
});

async function mounted(): Promise<ShallowRef<TextMeasurer | null>> {
    const { useMeasurer } = await import('../../../resources/js/canvas/useMeasurer');
    let measurer!: ShallowRef<TextMeasurer | null>;
    createApp(defineComponent({ setup: () => ((measurer = useMeasurer()), () => h('div')) })).mount({ children: [], parent: null });
    await nextTick();
    await Promise.resolve();
    await nextTick();

    return measurer;
}

describe('useMeasurer', () => {
    beforeEach(() => {
        create.mockReset();
    });

    it('holds the browser measurer once it is ready', async () => {
        const instance = { measure: () => ({ width: 1, height: 1 }) } as unknown as TextMeasurer;
        create.mockResolvedValue(instance);

        expect((await mounted()).value).toBe(instance);
    });

    it('stays empty without throwing when the measurer cannot be created', async () => {
        create.mockImplementation(async () => {
            throw new Error('fonts failed');
        });

        expect((await mounted()).value).toBeNull();
    });
});
