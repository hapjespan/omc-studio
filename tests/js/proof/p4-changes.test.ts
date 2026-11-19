import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { createEditor } from '@open-pencil/core/editor';
import { SceneGraph } from '@open-pencil/scene-graph';
import { changedIds, watchFinishedActions, type FinishedAction } from '../../../proof/wp-e0/changes';
import { computeBlocks, type Fixture } from '../../../proof/wp-e0/layout';
import { readBlocks } from '../../../proof/wp-e0/roundtrip';
import { buildScene } from '../../../proof/wp-e0/scene';

const fixture: Fixture = JSON.parse(readFileSync('proof/wp-e0/fixtures/scene.json', 'utf8'));
const fake = { width: (text: string, font: { size: number }) => text.length * font.size * 0.5 };

function load() {
    const graph = new SceneGraph();
    buildScene(graph, fixture, computeBlocks(fixture, fake));
    const editor = createEditor({ graph, skipInitialGraphSetup: true });
    return { graph, editor };
}

describe('P4 change notice', () => {
    it('notices a finished action from the host through events', () => {
        const { graph, editor } = load();
        const seen: FinishedAction[] = [];
        watchFinishedActions(editor, graph, (action) => seen.push(action));
        editor.updateNodeWithUndo('act-1', { x: 30 }, 'Move');
        expect(seen.length).toBe(1);
        expect(seen[0].ids).toContain('act-1');
    });

    it('stays silent while nothing was finished', () => {
        const { graph, editor } = load();
        const seen: FinishedAction[] = [];
        watchFinishedActions(editor, graph, (action) => seen.push(action));
        graph.updateNodePreview('act-1', { x: 31 });
        expect(seen).toEqual([]);
    });

    it('finds the changed identifiers by comparison when no event exists', () => {
        const { graph } = load();
        const before = readBlocks(graph);
        graph.updateNode('act-2', { x: 99 });
        graph.updateNode('dom-3', { width: 400 });
        expect(changedIds(before, readBlocks(graph)).sort()).toEqual(['act-2', 'dom-3']);
        expect(changedIds(before, before)).toEqual([]);
    });
});
