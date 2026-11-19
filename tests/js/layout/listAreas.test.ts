import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { flowList } from '../../../resources/js/layout/listAreas';
import { resolveStyle } from '../../../resources/js/layout/resolveStyle';
import { buildDomains } from '../../../resources/js/layout/tree';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';
import type { FlowContext } from '../../../resources/js/layout/flowDomains';

const context: FlowContext = { measurer: fakeMeasurer, style: (el, level) => resolveStyle(el.area, level) };
const elements = [
    element('g1', 'actors', 'domain', 'Klanten', null, 1),
    element('i1', 'actors', 'activity', 'Burgers', 'g1', 1),
    element('i2', 'actors', 'activity', 'Een heel lange naam van een item dat moet afbreken', 'g1', 2),
    element('g2', 'actors', 'domain', 'Leveranciers', null, 2),
];
const flow = () => flowList(buildDomains(elements, 'actors'), 'actors', 216, context);
const block = (id: string) => flow().blocks.find((candidate) => candidate.id === id)!;

describe('flowList', () => {
    it('stacks Groups inside the column without a fill and with Items below their Group', () => {
        expect(block('g2').y).toBeGreaterThan(block('i2').y);
        expect(block('i1').y).toBeGreaterThan(block('g1').y);
        expect(block('g1').style.fill).toBeNull();
        expect(block('g1').width).toBe(216);
        expect(block('g1').modality).toBeNull();
    });

    it('wraps Item names inside the column and grows the Group', () => {
        expect(block('i2').textLines.length).toBeGreaterThan(1);
        expect(block('i2').x + block('i2').width).toBeLessThanOrEqual(216);
        expect(block('g1').drawnHeight).toBeGreaterThanOrEqual(block('i2').y + block('i2').drawnHeight - block('g1').y);
    });

    it('keeps a Group without Items as a title only', () => {
        expect(block('g2').textLines).toEqual(['Leveranciers']);
        expect(block('g2').drawnHeight).toBe(18);
    });

    it('draws the list Area in the three left columns of the Artboard', () => {
        const result = computeCanvasLayout(withElements(elements), fakeMeasurer);
        const area = result.blocks.find((candidate) => candidate.id === 'actors')!;
        const group = result.blocks.find((candidate) => candidate.id === 'g1')!;
        expect(group.x).toBeGreaterThan(area.x);
        expect(group.x + group.width).toBeLessThanOrEqual(area.x + area.width);
    });
});
