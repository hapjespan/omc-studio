import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { flowDomains, type FlowContext } from '../../../resources/js/layout/flowDomains';
import { resolveStyle } from '../../../resources/js/layout/resolveStyle';
import { buildDomains } from '../../../resources/js/layout/tree';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';
import type { LayoutElement } from '../../../resources/js/layout/types';

const context: FlowContext = { measurer: fakeMeasurer, style: (el, level) => resolveStyle(el.area, level) };
const flow = (elements: LayoutElement[], width = 798) => flowDomains(buildDomains(elements, 'primary_activities'), 'primary_activities', width, context);
const domain = (id: string, name: string, position = 1) => element(id, 'primary_activities', 'domain', name, null, position);
const block = (blocks: ReturnType<typeof flow>['blocks'], id: string) => blocks.find((candidate) => candidate.id === id)!;

describe('flowDomains', () => {
    const three = [domain('a', 'Alfa', 1), domain('b', 'Beta', 2), domain('c', 'Gamma', 3)];

    it('gives cards of one Area equal width and flows them in rows', () => {
        const { blocks } = flow(three);
        const [a, b, c] = ['a', 'b', 'c'].map((id) => block(blocks, id));
        expect(new Set([a.width, b.width, c.width]).size).toBe(1);
        expect(b.y).toBe(a.y);
        expect(b.x).toBeGreaterThan(a.x);
        expect(c.y).toBeGreaterThan(a.y);
        expect(c.x).toBe(0);
    });

    it('lays Activities before Clusters whatever their order', () => {
        const { blocks } = flow([
            domain('d', 'Domein'),
            element('k', 'primary_activities', 'cluster', 'Cluster', 'd', 1),
            element('x', 'primary_activities', 'activity', 'Los', 'd', 2),
            element('y', 'primary_activities', 'activity', 'In cluster', 'k', 1),
        ]);
        expect(block(blocks, 'x').y).toBeLessThan(block(blocks, 'k').y);
        const cluster = block(blocks, 'k');
        const inside = block(blocks, 'y');
        expect(inside.y).toBeGreaterThan(cluster.y);
        expect(cluster.x).toBeGreaterThan(block(blocks, 'd').x);
        expect(cluster.style.fill).toBe('#E9DAC2');
    });

    const nested = (name: string) => [
        domain('d', 'Domein', 1),
        domain('e', 'Ander', 3),
        domain('f', 'Rij twee', 4),
        element('k', 'primary_activities', 'cluster', 'Cluster', 'd', 1),
        element('y', 'primary_activities', 'activity', name, 'k', 1),
    ];

    it('grows a block and every ancestor on a long name, never its width', () => {
        const short = flow(nested('Kort')).blocks;
        const long = flow(nested('woord '.repeat(40))).blocks;
        for (const id of ['y', 'k', 'd']) {
            expect(block(long, id).drawnHeight, id).toBeGreaterThan(block(short, id).drawnHeight);
            expect(block(long, id).width, id).toBe(block(short, id).width);
        }
        expect(block(long, 'y').textLines.length).toBeGreaterThan(1);
    });

    it('re-flows the sibling cards when a card grows', () => {
        const short = flow(nested('Kort')).blocks;
        const long = flow(nested('woord '.repeat(40))).blocks;
        expect(block(long, 'f').y).toBeGreaterThan(block(short, 'f').y);
        expect(block(long, 'e').drawnHeight).toBe(block(long, 'd').drawnHeight);
    });

    it('is empty without Domains', () => {
        expect(flow([])).toEqual({ blocks: [], height: 0 });
    });
});

describe('computeCanvasLayout with Domains', () => {
    it('draws the cards inside their Area in absolute Artboard pixels', () => {
        const result = computeCanvasLayout(withElements([domain('a', 'Alfa'), element('x', 'primary_activities', 'activity', 'Taak', 'a', 1)]), fakeMeasurer);
        const area = result.blocks.find((candidate) => candidate.id === 'primary_activities')!;
        const card = result.blocks.find((candidate) => candidate.id === 'a')!;
        expect(card.x).toBeGreaterThan(area.x);
        expect(card.y).toBeGreaterThan(area.y);
        expect(card.x + card.width).toBeLessThanOrEqual(area.x + area.width);
        expect(area.textLines).toEqual(['PRIMAIRE ACTIVITEITEN']);
    });
});
