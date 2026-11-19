import { readFileSync } from 'node:fs';
import { expect, it } from 'vitest';
import { createEditor } from '@open-pencil/core/editor';
import { SceneGraph } from '@open-pencil/scene-graph';
import { computeBlocks, type Fixture } from '../../../proof/wp-e0/layout';
import { buildScene } from '../../../proof/wp-e0/scene';

const fixture: Fixture = JSON.parse(readFileSync('proof/wp-e0/fixtures/scene.json', 'utf8'));
const fake = { width: (text: string, font: { size: number }) => text.length * font.size * 0.5 };

it('P5 control: without the restrictions the library creates, deletes and reparents', () => {
    const graph = new SceneGraph();
    buildScene(graph, fixture, computeBlocks(fixture, fake));
    const editor = createEditor({ graph, skipInitialGraphSetup: true });
    const before = [...graph.getAllNodes()].length;
    editor.createShape('RECTANGLE', 10, 10, 50, 50);
    expect([...graph.getAllNodes()].length).toBe(before + 1);
    graph.reparentNode('act-3', 'dom-2');
    expect(graph.getNode('act-3')!.parentId).toBe('dom-2');
    editor.select(['act-1']);
    editor.deleteSelected();
    expect(graph.getNode('act-1')).toBeUndefined();
    editor.setTool('RECTANGLE');
    expect(editor.state.activeTool).toBe('RECTANGLE');
});
