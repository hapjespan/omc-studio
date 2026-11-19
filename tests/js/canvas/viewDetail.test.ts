import { describe, expect, it } from 'vitest';
import { detailOf } from '../../../resources/js/canvas/ViewDetail.vue';
import { eightAreas, element } from '../layout/support';

const source = {
    areas: eightAreas,
    elements: [
        { ...element('a', 'primary_activities', 'activity', 'Les geven', 'd', 1, { modality: 'A' }), description: 'Voor de klas', rationale: null },
        element('d', 'primary_activities', 'domain', 'Onderwijs'),
        element('c', 'primary_activities', 'cluster', 'Lessen', 'd'),
        element('u', 'supporting_activities', 'domain', 'Onbekend', null, 1, { unknownAllocation: true }),
    ],
    statements: [
        { kind: 'mission' as const, title: null, body: 'Leren', position: 1 },
        { kind: 'goal' as const, title: 'Groei', body: 'Meer leerlingen', position: 2 },
        { kind: 'goal' as const, title: null, body: 'Minder verzuim', position: 1 },
    ],
    modalities: [{ key: 'adaptive', letter: 'A', colour: '#1E5FBF' }],
};

describe('detailOf', () => {
    it('lists the fields of an Element with its Modality mark and letter', () => {
        const detail = detailOf('a', source)!;
        expect(detail.title).toBe('Les geven');
        expect(detail.kind).toBe('Activiteit');
        expect(detail.modality).toMatchObject({ letter: 'A', colour: '#1E5FBF', name: 'Adaptive' });
        expect(detail.fields).toEqual([
            { label: 'Naam', value: 'Les geven' },
            { label: 'Omschrijving', value: 'Voor de klas' },
            { label: 'Onderbouwing', value: 'Niet ingevuld' },
            { label: 'Modaliteit', value: 'Adaptive' },
        ]);
    });

    it('names the level of a Domain and a Cluster and flags the system Domain', () => {
        expect(detailOf('d', source)!.kind).toBe('Domein');
        expect(detailOf('c', source)!.kind).toBe('Cluster');
        expect(detailOf('u', source)!.system).toBe(true);
    });

    it('shows an Area by name and the Direction band with the full statements in order', () => {
        expect(detailOf('primary_activities', source)!.fields).toEqual([{ label: 'Naam', value: 'Primaire activiteiten' }]);
        const direction = detailOf('direction', source)!;
        expect(direction.fields.map((field) => field.value)).toEqual(['Koers', 'Leren', 'Niet ingevuld', '1. Minder verzuim\n2. Groei Meer leerlingen']);
    });

    it('returns null for an unknown id', () => {
        expect(detailOf('missing', source)).toBeNull();
    });
});
