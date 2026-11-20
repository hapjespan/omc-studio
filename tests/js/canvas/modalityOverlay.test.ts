import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import CanvasView from '../../../resources/js/canvas/CanvasView.vue';
import { modalityCounts } from '../../../resources/js/canvas/overlay/counts';
import { countsLine } from '../../../resources/js/canvas/overlay/ModalityOverlay.vue';
import OverlayLegend from '../../../resources/js/canvas/overlay/OverlayLegend.vue';
import { contrastRatio, defaultColours } from '../../../resources/js/colour';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { eightAreas, element, emptyInput } from '../layout/support';

const letters = ['C', 'A', 'S', 'D'];
const modalities = [
    { key: 'common', letter: 'C', colour: '#1F7A4D' },
    { key: 'adaptive', letter: 'A', colour: '#1E5FBF' },
    { key: 'specialized', letter: 'S', colour: '#B4540A' },
    { key: 'distinct', letter: 'D', colour: '#6B3FB5' },
];

describe('modalityCounts', () => {
    it('counts the Clusters and Activities of a Domain per Modality and without one', () => {
        const elements = [
            element('d', 'primary_activities', 'domain', 'D'),
            element('c', 'primary_activities', 'cluster', 'C', 'd', 1, { modality: 'C' }),
            element('a', 'primary_activities', 'activity', 'A', 'c', 1, { modality: 'C' }),
            element('b', 'primary_activities', 'activity', 'B', 'd', 2, { modality: 'C' }),
            element('e', 'primary_activities', 'activity', 'E', 'd', 3, { modality: 'A' }),
            element('f', 'primary_activities', 'activity', 'F', 'd', 4),
            element('g', 'primary_activities', 'activity', 'G', 'd', 5),
        ];
        const counts = modalityCounts(elements, letters).get('d')!;
        expect(countsLine(counts, letters)).toBe('C 3, A 1, S 0, D 0, geen 2');
    });
});

describe('modality marks', () => {
    it('keep the letter at 4.5:1 on its colour and the colour at 3:1 on every default card fill', () => {
        const fills = Object.entries(defaultColours.areas).filter(([key]) => key !== 'direction').flatMap(([, levels]) => Object.values(levels)).filter((fill): fill is string => fill !== null);
        for (const { colour } of modalities) {
            expect(contrastRatio(colour, '#FFFFFF')).toBeGreaterThanOrEqual(4.5);
            for (const fill of fills) expect(contrastRatio(colour, fill)).toBeGreaterThanOrEqual(3);
        }
    });

    it('are left off the list Areas', async () => {
        const elements = [element('l', 'actors', 'domain', 'Leerlingen', null, 1, { modality: 'C' }), element('p', 'primary_activities', 'domain', 'Onderwijs', null, 1, { modality: 'C' })];
        const drawn = computeCanvasLayout({ ...emptyInput(), elements }, fakeMeasurer);
        const html = await renderToString(createSSRApp({ render: () => h(CanvasView, { drawn, areas: eightAreas, elements, statements: [], modalities }) }));
        const list = /data-block="l"[\s\S]*?<\/button>/.exec(html)![0];
        expect(list).not.toContain('aria-hidden="true">C<');
        expect(/data-block="p"[\s\S]*?<\/button>/.exec(html)![0]).toContain('aria-hidden="true">C<');
    });
});

describe('modality legend', () => {
    it('lists the four Modalities with colour, letter and name in text', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(OverlayLegend, { overlay: 'modality', modalities }) }));
        for (const text of ['C: Common', 'A: Adaptive', 'S: Specialized', 'D: Distinct']) expect(html).toContain(text);
    });
});
