import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import OverlayFilters, { activeFilters } from '../../../resources/js/canvas/overlay/OverlayFilters.vue';

const options = { costKinds: ['personnel', 'project'], owners: [{ id: 3, name: 'Afdeling ICT' }], applications: [{ id: 7, name: 'Roosterpakket' }] };

describe('activeFilters', () => {
    it('names every filter that is set', () => {
        expect(activeFilters({ costKind: 'personnel', ownerId: 3, applicationId: null }, options)).toEqual(['Soort kosten: Personeel', 'Eigenaar: Afdeling ICT']);
        expect(activeFilters({ costKind: null, ownerId: null, applicationId: 7 }, options)).toEqual(['Applicatie: Roosterpakket']);
        expect(activeFilters({ costKind: null, ownerId: null, applicationId: null }, options)).toEqual([]);
    });
});

describe('OverlayFilters', () => {
    it('offers three chips with a single choice each with Alle, applies only after a change and announces the active filters', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(OverlayFilters, { filters: { costKind: null, ownerId: 3, applicationId: null }, options }) }));
        for (const label of ['Soort kosten', 'Eigenaar', 'Applicatie']) expect(html).toContain(label);
        expect(html.match(/>Alle</g)).toHaveLength(3);
        expect(html).toContain('<option value="3">Afdeling ICT</option>');
        expect(html).toContain('aria-label="Soort kosten"');
        expect(html).not.toContain('Filteren</button>');
        expect(html).not.toContain('onchange');
        expect(html).toContain('Actieve filters: Eigenaar: Afdeling ICT');
    });
});
