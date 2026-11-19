import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { SceneGraph } from '@open-pencil/scene-graph';
import { computeBlocks, type Fixture } from '../../../proof/wp-e0/layout';
import { buildScene, colorToHex, TEXT_SUFFIX } from '../../../proof/wp-e0/scene';

const fixture: Fixture = JSON.parse(readFileSync('proof/wp-e0/fixtures/scene.json', 'utf8'));
const fake = { width: (text: string, font: { size: number }) => text.length * font.size * 0.5 };

function build() {
    const graph = new SceneGraph();
    const blocks = computeBlocks(fixture, fake);
    buildScene(graph, fixture, blocks);
    return { graph, blocks };
}

describe('P2 scene from data', () => {
    it('holds 3 Domains, 1 Cluster and 10 Activities under the artboard', () => {
        const { graph } = build();
        const kinds = (kind: string) => fixture.blocks.filter((block) => block.kind === kind).length;
        expect([kinds('domain'), kinds('cluster'), kinds('activity')]).toEqual([3, 1, 10]);
        expect(graph.getNode('artboard')?.childIds.length).toBe(5);
    });

    it('keeps the identifier, colour, font and text of every block', () => {
        const { graph, blocks } = build();
        for (const block of blocks) {
            const node = graph.getNode(block.id)!;
            expect(colorToHex(node.fills[0].color)).toBe(block.fill);
            if (block.lines.length === 0) continue;
            const text = graph.getNode(block.id + TEXT_SUFFIX)!;
            expect(text.text).toBe(block.lines.join('\n'));
            expect([text.fontFamily, text.fontSize, text.fontWeight]).toEqual([block.font.family, block.font.size, block.font.weight]);
        }
    });

    it('uses only Inter and Space Grotesk', () => {
        expect(new Set(fixture.blocks.map((block) => block.fontFamily))).toEqual(new Set(['Inter', 'Space Grotesk']));
    });

    it('grows an Activity with its text and never beyond the width', () => {
        const { blocks } = build();
        const long = blocks.find((block) => block.id === 'act-2')!;
        const short = blocks.find((block) => block.id === 'act-3')!;
        expect(long.lines.length).toBeGreaterThan(short.lines.length);
        expect(long.height).toBe(2 * long.padding + long.lines.length * long.lineHeight);
        expect(long.lines.every((line) => fake.width(line, long.font) <= long.width - 2 * long.padding)).toBe(true);
    });
});
