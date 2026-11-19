import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import { areaLine } from '../../../resources/js/canvas/overlay/FinancialCard.vue';
import OverlayLegend from '../../../resources/js/canvas/overlay/OverlayLegend.vue';
import { shareColour } from '../../../resources/js/canvas/overlay/ShareColour';
import { contrastRatio } from '../../../resources/js/colour';

const luminanceSum = (hex: string): number => [1, 3, 5].reduce((sum, index) => sum + parseInt(hex.slice(index, index + 2), 16), 0);

describe('shareColour', () => {
    const fills = ['#F6E6CC', '#D9E9F2', '#FFFFFF'];

    it('gets darker as the Share rises', () => {
        const shares = [0, 300, 800, 1500, 2500, 5000];
        const sums = shares.map((share) => luminanceSum(shareColour(share, '#F6E6CC').background));
        for (let index = 1; index < sums.length; index++) expect(sums[index]).toBeLessThan(sums[index - 1]);
    });

    it('tints with #0E4D64 from 3% to 28% and keeps ink text at 10.5:1 on the darkest step', () => {
        for (const base of fills) {
            expect(shareColour(0, base).background).toBe(mixHex('#FFFFFF', 0.03));
            const darkest = shareColour(10000, base);
            expect(darkest.background).toBe(mixHex('#FFFFFF', 0.28));
            expect(contrastRatio(darkest.background, darkest.text)).toBeGreaterThanOrEqual(10.5);
        }
    });

    it('keeps the text at 4.5:1 for every Share and base colour', () => {
        for (const base of [...fills, null]) {
            for (let share = 0; share <= 10000; share += 250) {
                const { background, text } = shareColour(share, base);
                expect(contrastRatio(background, text)).toBeGreaterThanOrEqual(4.5);
            }
        }
    });

    it('keeps the base colour when there is no Share', () => {
        expect(shareColour(null, '#F6E6CC').background).toBe('#F6E6CC');
    });
});

const mixHex = (base: string, opacity: number): string => {
    const tint = [0x0e, 0x4d, 0x64];
    return `#${[1, 3, 5].map((index, channel) => Math.round(parseInt(base.slice(index, index + 2), 16) + (tint[channel] - parseInt(base.slice(index, index + 2), 16)) * opacity).toString(16).padStart(2, '0')).join('').toUpperCase()}`;
};

describe('areaLine and OverlayLegend', () => {
    it('shows the Area Total in whole euros with its Share', () => {
        const total = { budget: 123456, forecast: 0, run: 0, change: 0, undetermined: 0 };
        expect(areaLine(total, { ...total, budget: 400000 })).toBe('Totaal € 1.235 · 30,9%');
        expect(areaLine(total, { ...total, budget: 0 })).toBe('Totaal € 1.235 · -');
    });

    it('explains the colour scale, the bar and that list Areas carry no costs', async () => {
        const html = await renderToString(createSSRApp({ render: () => h(OverlayLegend, { overlay: 'financial', modalities: [] }) }));
        expect(html).toContain('Aandeel van totaal laag naar hoog');
        expect(html).toContain('streep');
        expect(html).toContain('geen kosten');
    });
});
