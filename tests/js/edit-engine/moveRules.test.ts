import { describe, expect, it } from 'vitest';
import { moveRefusal, type RuleNode } from '../../../resources/js/edit-engine/moveRules';
import { moveTargets } from '../../../resources/js/pages/Drawing/moveTargets';
import fixture from '../../fixtures/move-rules.json';
import { eightAreas, element } from '../layout/support';

const nodes: RuleNode[] = fixture.elements.map((row) => ({
    id: row.id,
    parentId: row.parent,
    kind: row.kind as RuleNode['kind'],
    areaKind: fixture.areas[row.area as keyof typeof fixture.areas] as RuleNode['areaKind'],
    name: row.name,
    unknownAllocation: 'unknownAllocation' in row,
}));
const byId = (id: string): RuleNode => nodes.find((node) => node.id === id) as RuleNode;

describe('move rules fixture', () => {
    it.each(fixture.cases)('$dragged onto $target gives $ts', (row) => {
        expect(moveRefusal(nodes, byId(row.dragged), byId(row.target))).toBe(row.ts);
    });

    it('offers the same targets as the shared rule allows', () => {
        const layout = fixture.elements.map((row, position) => element(row.id, row.area as never, row.kind as never, row.name, row.parent, position, 'unknownAllocation' in row ? { unknownAllocation: true } : {}));
        const dragged = ['a1', 'c1', 'a3', 'i1'];

        for (const id of dragged) {
            const offered = moveTargets(id, eightAreas, layout).map((target) => target.id);
            const allowed = fixture.cases.filter((row) => row.dragged === id && row.ts === null).map((row) => row.target);

            expect(offered).toEqual(expect.arrayContaining(allowed));
            expect(fixture.cases.filter((row) => row.dragged === id && row.ts !== null).map((row) => row.target).filter((target) => offered.includes(target))).toEqual([]);
        }
    });
});
