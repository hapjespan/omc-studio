import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { computeBlocks, type Fixture } from '../../../proof/wp-e0/layout';
import { judge, landing, placeBlocks } from '../../../proof/wp-e0/shift-move';

const fixture: Fixture = JSON.parse(readFileSync('proof/wp-e0/fixtures/scene.json', 'utf8'));
const fake = { width: (text: string, font: { size: number }) => text.length * font.size * 0.5 };
const placed = placeBlocks(computeBlocks(fixture, fake));
const at = (id: string, dx = 10, dy = 10) => {
    const block = placed.find((entry) => entry.id === id)!;
    return { x: block.ax + dx, y: block.ay + dy };
};

describe('P10 Shift move', () => {
    it('finds the Domain under the pointer for an Activity and labels it', () => {
        expect(judge(placed, 'act-3', at('dom-3', 20, 650))).toEqual({ targetId: 'dom-3', valid: true, label: 'Naar Supporting activities' });
    });

    it('prefers the innermost container: a Cluster for an Activity', () => {
        expect(judge(placed, 'act-8', at('clu-1', 30, 400))).toEqual({ targetId: 'clu-1', valid: true, label: 'Naar Order to delivery' });
        expect(judge(placed, 'act-5', at('clu-1', 30, 400))).toEqual({ targetId: 'clu-1', valid: false, label: 'Kan hier niet: ander soort gebied' });
    });

    it('refuses another kind of area with the reason', () => {
        expect(judge(placed, 'act-3', at('dom-2', 20, 650))).toEqual({ targetId: 'dom-2', valid: false, label: 'Kan hier niet: ander soort gebied' });
    });

    it('treats a Cluster under the pointer as its Domain for a Cluster', () => {
        const fromOther = judge(placed, 'clu-1', at('dom-3', 20, 650));
        expect(fromOther).toEqual({ targetId: 'dom-3', valid: true, label: 'Naar Supporting activities' });
    });

    it('is no move when the pointer is over the current parent, over nothing or over the block itself', () => {
        expect(judge(placed, 'act-3', at('dom-1', 20, 650))).toBeNull();
        expect(judge(placed, 'act-3', { x: 5, y: 1100 })).toBeNull();
        expect(judge(placed, 'act-3', at('act-3', 5, 5))).toBeNull();
    });

    it('does not move a Domain', () => {
        expect(judge(placed, 'dom-1', at('dom-3', 20, 650))).toBeNull();
    });

    it('refuses a name that already exists in the target', () => {
        const clones = placed.map((block) => (block.id === 'act-8' ? { ...block, name: 'Plan production' } : block));
        const moved = clones.map((block) => (block.id === 'act-3' ? { ...block, parent: 'dom-3' } : block));
        expect(judge(moved, 'act-8', at('dom-3', 20, 650))).toBeNull();
        const other = clones.map((block) => (block.id === 'act-3' ? { ...block, name: 'Plan production', parent: 'dom-3' } : block.id === 'act-1' ? { ...block, name: 'plan production' } : block));
        expect(judge(other, 'act-1', at('dom-3', 20, 650))).toEqual({ targetId: 'dom-3', valid: false, label: 'Kan hier niet: de naam bestaat daar al' });
    });

    it('says the block gets narrower when it is wider than the target', () => {
        const wide = placed.map((block) => (block.id === 'act-3' ? { ...block, width: 520 } : block));
        expect(judge(wide, 'act-3', at('dom-3', 20, 650))?.label).toBe('Naar Supporting activities (wordt smaller)');
    });

    it('pushes the landing spot inside the target and narrows a wider block', () => {
        const target = placed.find((block) => block.id === 'dom-3')!;
        expect(landing(placed, 'act-3', 'dom-3', { x: target.ax + 400, y: target.ay + 900 })).toEqual({ x: 32, y: 656, width: 468, height: 44 });
        expect(landing(placed, 'act-3', 'dom-3', { x: target.ax - 50, y: target.ay - 50 })).toEqual({ x: 0, y: 0, width: 468, height: 44 });
        const wide = placed.map((block) => (block.id === 'act-3' ? { ...block, width: 520 } : block));
        expect(landing(wide, 'act-3', 'dom-3', { x: target.ax + 30, y: target.ay + 30 }).width).toBe(500);
    });
});
