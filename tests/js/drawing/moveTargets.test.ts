import { describe, expect, it } from 'vitest';
import { moveTargets, pathOf } from '../../../resources/js/pages/Drawing/moveTargets';
import { eightAreas, element } from '../layout/support';

const elements = [
    element('d1', 'primary_activities', 'domain', 'Inkoop'),
    element('c1', 'primary_activities', 'cluster', 'Werving', 'd1', 1),
    element('a1', 'primary_activities', 'activity', 'Offreren', 'c1', 1),
    element('d2', 'primary_activities', 'domain', 'Verkoop', null, 2),
    element('c2', 'primary_activities', 'cluster', 'Klanten', 'd2', 1),
    element('a2', 'primary_activities', 'activity', 'Offreren', 'd2', 2),
    element('d3', 'supporting_activities', 'domain', 'Beheer', null, 1),
    element('u', 'supporting_activities', 'domain', 'Onbekende allocatie', null, 2, { unknownAllocation: true }),
    element('g1', 'actors', 'domain', 'Klanten', null, 1),
    element('g2', 'actors', 'domain', 'Partners', null, 2),
    element('i1', 'actors', 'activity', 'Jan', 'g1', 1),
];
const ids = (id: string) => moveTargets(id, eightAreas, elements).map((target) => target.id);

describe('moveTargets', () => {
    it('offers a Cluster the Domains of any activity Area except its own and the Unknown allocation', () => {
        expect(ids('c1')).toEqual(['d2', 'd3']);
    });

    it('offers an Activity Domains and Clusters other than its parent, without name clashes', () => {
        expect(ids('a1')).toEqual(['d1', 'c2', 'd3']);
        expect(ids('a2')).toEqual(['d1', 'c2', 'd3']);
    });

    it('offers an Item the other Groups of a list Area only', () => {
        expect(ids('i1')).toEqual(['g2']);
    });

    it('offers nothing for a Domain', () => {
        expect(ids('d1')).toEqual([]);
    });

    it('names the target by its path', () => {
        expect(pathOf('c2', eightAreas, elements)).toBe('Primaire activiteiten › Verkoop › Klanten');
    });
});
