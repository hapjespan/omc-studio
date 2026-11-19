import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { createEditor } from '@open-pencil/core/editor';
import { SceneGraph } from '@open-pencil/scene-graph';
import { alignBlocks, distributeBlocks, dragBlocks, resizeBlock, restyleBlock } from '../../../proof/wp-e0/history';
import { computeBlocks, type Fixture } from '../../../proof/wp-e0/layout';
import { readBlocks } from '../../../proof/wp-e0/roundtrip';
import { buildScene } from '../../../proof/wp-e0/scene';

const fixture: Fixture = JSON.parse(readFileSync('proof/wp-e0/fixtures/scene.json', 'utf8'));
const fake = { width: (text: string, font: { size: number }) => text.length * font.size * 0.5 };

function load() {
    const graph = new SceneGraph();
    buildScene(graph, fixture, computeBlocks(fixture, fake));
    const editor = createEditor({ graph, skipInitialGraphSetup: true });
    return { graph, editor, state: () => JSON.stringify(readBlocks(graph)) };
}

type Context = ReturnType<typeof load>;

const unevenForAlign = ({ graph }: Context) => {
    graph.updateNode('act-6', { x: 50 });
    graph.updateNode('act-7', { x: 90 });
};
const unevenForDistribute = ({ graph }: Context) => {
    graph.updateNode('act-6', { y: 150 });
    graph.updateNode('act-7', { y: 330 });
};

const prepared: Record<string, (ctx: Context) => void> = { align: unevenForAlign, distribute: unevenForDistribute };

const actions: [string, (ctx: Context) => void][] = [
    ['drag', ({ graph, editor }) => dragBlocks(editor, graph, ['act-5'], 20, 30)],
    ['size', ({ graph, editor }) => resizeBlock(editor, graph, 'act-6', 400, 60)],
    ['style', ({ editor }) => restyleBlock(editor, 'act-7', '#00ff00')],
    ['align', ({ editor }) => alignBlocks(editor, ['act-5', 'act-6', 'act-7'], 'horizontal', 'min')],
    ['distribute', ({ editor }) => distributeBlocks(editor, ['act-5', 'act-6', 'act-7'], 'vertical')],
];

describe('P8 undo and redo', () => {
    for (const [name, perform] of actions) {
        it(`undoes and redoes a ${name} exactly once`, () => {
            const ctx = load();
            prepared[name]?.(ctx);
            const before = ctx.state();
            perform(ctx);
            const after = ctx.state();
            expect(after).not.toBe(before);
            ctx.editor.undoAction();
            expect(ctx.state()).toBe(before);
            ctx.editor.undoAction();
            expect(ctx.state()).toBe(before);
            ctx.editor.redoAction();
            expect(ctx.state()).toBe(after);
            ctx.editor.redoAction();
            expect(ctx.state()).toBe(after);
        });
    }

    it('steps back one finished action at a time over all five', () => {
        const ctx = load();
        unevenForAlign(ctx);
        unevenForDistribute(ctx);
        const states = [ctx.state()];
        for (const [, perform] of actions) {
            perform(ctx);
            states.push(ctx.state());
        }
        for (let step = actions.length - 1; step >= 0; step--) {
            ctx.editor.undoAction();
            expect(ctx.state(), `undo to before ${actions[step][0]}`).toBe(states[step]);
        }
        for (let step = 1; step <= actions.length; step++) {
            ctx.editor.redoAction();
            expect(ctx.state(), `redo of ${actions[step - 1][0]}`).toBe(states[step]);
        }
    });

    it('moves several selected blocks as one step', () => {
        const ctx = load();
        const before = ctx.state();
        dragBlocks(ctx.editor, ctx.graph, ['act-5', 'act-6'], 10, 10);
        expect(ctx.state()).not.toBe(before);
        ctx.editor.undoAction();
        expect(ctx.state()).toBe(before);
    });
});
