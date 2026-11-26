import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { SceneGraph } from '@open-pencil/scene-graph';
import { computeBlocks, type Fixture } from '../../../proof/wp-e0/layout';
import { readBlocks } from '../../../proof/wp-e0/roundtrip';
import { buildScene } from '../../../proof/wp-e0/scene';

const fixture: Fixture = JSON.parse(readFileSync('proof/wp-e0/fixtures/scene.json', 'utf8'));
const fake = { width: (text: string, font: { size: number }) => text.length * font.size * 0.5 };

function expected() {
    return computeBlocks(fixture, fake).map((block) => ({
        id: block.id,
        parent: block.parent,
        x: block.x,
        y: block.y,
        width: block.width,
        height: block.height,
        fill: block.fill,
        textColor: block.lines.length ? block.textColor : null,
        fontFamily: block.lines.length ? block.font.family : null,
        fontSize: block.lines.length ? block.font.size : null,
        fontWeight: block.lines.length ? block.font.weight : null,
        lines: block.lines,
    }));
}

function load() {
    const graph = new SceneGraph();
    buildScene(graph, fixture, computeBlocks(fixture, fake));
    return graph;
}

describe('P3 round trip', () => {
    it('reads back place, size, style and text of every node with its identifier, without difference', () => {
        const read = readBlocks(load());
        const byId = new Map(read.map((block) => [block.id, block]));
        expect(read.length).toBe(fixture.blocks.length);
        for (const block of expected()) expect(byId.get(block.id)).toEqual(block);
    });

    it('is stable: reading, rebuilding from the read data and reading again gives the same data', () => {
        const first = readBlocks(load());
        const graph = new SceneGraph();
        const rebuilt = first.map((block) => ({
            ...fixture.blocks.find((candidate) => candidate.id === block.id)!,
            x: block.x,
            y: block.y,
            width: block.width,
        }));
        buildScene(graph, fixture, computeBlocks({ ...fixture, blocks: rebuilt }, fake));
        expect(readBlocks(graph)).toEqual(first);
    });

    it('reads a change made through the graph', () => {
        const graph = load();
        graph.updateNode('act-3', { x: 40, y: 99, width: 300 });
        graph.updateNode('act-3', { fills: [{ type: 'SOLID', color: { r: 1, g: 0, b: 0, a: 1 }, opacity: 1, visible: true }] });
        const block = readBlocks(graph).find((candidate) => candidate.id === 'act-3')!;
        expect([block.x, block.y, block.width, block.fill]).toEqual([40, 99, 300, '#ff0000']);
    });
});
