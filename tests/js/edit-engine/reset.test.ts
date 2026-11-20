import { describe, expect, it } from 'vitest';
import { resetIds } from '../../../resources/js/edit-engine/commands/reset';
import { element } from '../layout/support';

const elements = [
    element('d1', 'primary_activities', 'domain', 'Inkoop'),
    element('c1', 'primary_activities', 'cluster', 'Werving', 'd1', 1),
    element('a1', 'primary_activities', 'activity', 'Offreren', 'c1', 1),
    element('d2', 'primary_activities', 'domain', 'Verkoop', null, 2),
];

describe('resetIds', () => {
    it('names only the Element for the Element scope', () => {
        expect(resetIds(elements, 'element', 'c1')).toEqual(['c1']);
    });

    it('names the Domain and everything below it for the Domain scope', () => {
        expect(resetIds(elements, 'domain', 'd1')).toEqual(['d1', 'c1', 'a1']);
    });

    it('names every Element for the Canvas scope and none for an unknown id', () => {
        expect(resetIds(elements, 'canvas', null)).toHaveLength(4);
        expect(resetIds(elements, 'element', 'x')).toEqual([]);
    });
});
