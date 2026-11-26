import { describe, expect, it } from 'vitest';
import { buildDomains } from '../../../resources/js/layout/tree';
import { element } from './support';
import type { LayoutElement } from '../../../resources/js/layout/types';

const names = (nodes: ReturnType<typeof buildDomains>): string[] => nodes.map((node) => node.element.id);

describe('buildDomains', () => {
    it('takes the Domains of the asked Area only, in position order', () => {
        const elements = [
            element('b', 'primary_activities', 'domain', 'Beta', null, 2),
            element('a', 'primary_activities', 'domain', 'Alfa', null, 1),
            element('s', 'steering', 'domain', 'Stuur', null, 1),
        ];

        expect(names(buildDomains(elements, 'primary_activities'))).toEqual(['a', 'b']);
        expect(names(buildDomains(elements, 'steering'))).toEqual(['s']);
    });

    it('breaks a tie on position with the id', () => {
        const elements = [element('z', 'primary_activities', 'domain', 'Zeta', null, 1), element('m', 'primary_activities', 'domain', 'Mu', null, 1)];

        expect(names(buildDomains(elements, 'primary_activities'))).toEqual(['m', 'z']);
    });

    it('puts the Unknown allocation Domain last', () => {
        const elements = [
            element('u', 'primary_activities', 'domain', 'Onbekend', null, 1, { unknownAllocation: true }),
            element('a', 'primary_activities', 'domain', 'Alfa', null, 9),
        ];

        expect(names(buildDomains(elements, 'primary_activities'))).toEqual(['a', 'u']);
    });

    it('lays the Activities of a Domain before its Clusters and nests three levels deep', () => {
        const elements: LayoutElement[] = [
            element('d', 'primary_activities', 'domain', 'Domein'),
            element('k', 'primary_activities', 'cluster', 'Cluster', 'd', 1),
            element('x', 'primary_activities', 'activity', 'Los', 'd', 2),
            element('y', 'primary_activities', 'activity', 'In cluster', 'k', 1),
        ];
        const [domain] = buildDomains(elements, 'primary_activities');

        expect(domain.children.map((node) => node.element.id)).toEqual(['x', 'k']);
        expect(domain.children[1].children.map((node) => node.element.id)).toEqual(['y']);
    });

    it('leaves out an Element whose parent is not in the list', () => {
        const elements = [element('d', 'primary_activities', 'domain', 'Domein'), element('o', 'primary_activities', 'activity', 'Wees', 'weg', 1)];
        const [domain] = buildDomains(elements, 'primary_activities');

        expect(domain.children).toEqual([]);
    });

    it('keeps a child that names another Area than its parent', () => {
        const elements = [element('d', 'primary_activities', 'domain', 'Domein'), element('c', 'steering', 'activity', 'Elders', 'd', 1)];
        const [domain] = buildDomains(elements, 'primary_activities');

        expect(domain.children.map((node) => node.element.id)).toEqual(['c']);
    });
});
