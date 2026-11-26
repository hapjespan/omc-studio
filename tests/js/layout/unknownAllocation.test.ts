import { describe, expect, it } from 'vitest';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from './fakeMeasurer';
import { element, withElements } from './support';

const unknown = element('u', 'supporting_activities', 'domain', 'Onbekende allocatie', null, 1, { unknownAllocation: true });

describe('Unknown allocation card', () => {
    it('is the last card of Supporting activities, flagged as system', () => {
        const result = computeCanvasLayout(
            withElements([unknown, element('a', 'supporting_activities', 'domain', 'ICT', null, 2), element('b', 'supporting_activities', 'domain', 'HR', null, 3)]),
            fakeMeasurer,
        );
        const cards = result.blocks.filter((block) => block.kind === 'domain');
        const last = cards.reduce((a, b) => (b.y > a.y || (b.y === a.y && b.x > a.x) ? b : a));
        expect(last.id).toBe('u');
        expect(last.system).toBe(true);
        expect(cards.filter((card) => card.system)).toHaveLength(1);
    });

    it('stands alone without a Warning when the Area has no other Domain', () => {
        const result = computeCanvasLayout(withElements([unknown]), fakeMeasurer);
        expect(result.blocks.find((block) => block.id === 'u')).toMatchObject({ system: true, textLines: ['Onbekende allocatie'] });
        expect(result.warnings).toEqual([]);
        expect(result.beyondArtboardCount).toBe(0);
    });
});
