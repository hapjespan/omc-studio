import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import FinancialCard, { cardLines, countsText, fitParts } from '../../../resources/js/canvas/overlay/FinancialCard.vue';
import type { ElementFigures, Money } from '../../../resources/js/canvas/overlay/figures';
import { descendantCounts } from '../../../resources/js/canvas/overlay/counts';
import { computeCanvasLayout } from '../../../resources/js/layout';
import { fakeMeasurer } from '../layout/fakeMeasurer';
import { element, emptyInput } from '../layout/support';

const money = (budget: number, forecast: number): Money => ({ budget, forecast, run: 0, change: 0, undetermined: 0 });
const figures = (budget: number, forecast: number, shareBp: number | null): ElementFigures => ({ direct: money(budget, forecast), total: money(budget, forecast), shareBp, fteHundredths: 0 });

describe('cardLines', () => {
    it('shows whole euros, a one decimal Share and the forecast against the budget', () => {
        const lines = cardLines(figures(123456, 110000, 1235), { activities: 3, clusters: 1 }, 2026);
        expect(lines.total).toBe('Totaal € 1.235');
        expect(lines.header).toBe('€ 1.235 · 12,4% van totaal');
        expect(lines.share).toBe('12,4% van totaal');
        expect(lines.forecast).toBe('Prognose € 1.100 · 89% van budget');
        expect(lines.counts).toBe('3 activiteiten · 1 cluster');
        expect(lines.empty).toBeNull();
    });

    it('leaves out a zero cluster count, keeps the singular and always names the activities', () => {
        expect(countsText({ activities: 2, clusters: 0 })).toBe('2 activiteiten');
        expect(countsText({ activities: 1, clusters: 2 })).toBe('1 activiteit · 2 clusters');
        expect(countsText({ activities: 0, clusters: 0 })).toBe('0 activiteiten');
    });

    it('says that no costs are allocated for the year', () => {
        expect(cardLines(figures(0, 0, null), { activities: 0, clusters: 0 }, 2026).empty).toBe('Geen kosten toegewezen in 2026');
    });
});

describe('fitParts', () => {
    it('drops counts, then the forecast line, then the bar as the card gets smaller and never the Total', () => {
        expect([...fitParts(200)]).toEqual(['counts', 'forecast', 'bar']);
        expect([...fitParts(40)].sort()).toEqual(['bar', 'forecast']);
        expect([...fitParts(20)]).toEqual(['bar']);
        expect([...fitParts(5)]).toEqual([]);
    });
});

describe('FinancialCard', () => {
    it('draws the bar with the Forecast fill and the Budget stripe', async () => {
        const drawn = computeCanvasLayout({ ...emptyInput(), elements: [element('d', 'primary_activities', 'domain', 'Onderwijs')] }, fakeMeasurer);
        const block = { ...drawn.blocks.find((candidate) => candidate.id === 'd')!, drawnHeight: 200 };
        const html = await renderToString(createSSRApp({ render: () => h(FinancialCard, { block, figures: figures(1000, 500, 1000), counts: { activities: 0, clusters: 0 }, year: 2026 }) }));
        expect(html).toContain('data-bar-fill');
        expect(html).toContain('width:41.666');
        expect(html).toContain('left:83.333');
        expect(html).not.toContain('data-bar-over');
        expect(html).toContain('>€ 10<');
        expect(html).toContain('>10,0% van totaal<');
    });

    it('shows the part of the forecast above the budget in the contract amber', async () => {
        const drawn = computeCanvasLayout({ ...emptyInput(), elements: [element('d', 'primary_activities', 'domain', 'Onderwijs')] }, fakeMeasurer);
        const block = { ...drawn.blocks.find((candidate) => candidate.id === 'd')!, drawnHeight: 200 };
        const html = await renderToString(createSSRApp({ render: () => h(FinancialCard, { block, figures: figures(1000, 1100, 1000), counts: { activities: 0, clusters: 0 }, year: 2026 }) }));
        expect(html).toContain('data-bar-over');
        expect(html).toContain('#9A7811');
    });
});

describe('descendantCounts', () => {
    it('counts the Activities and Clusters under every Domain', () => {
        const elements = [element('d', 'primary_activities', 'domain', 'D'), element('c', 'primary_activities', 'cluster', 'C', 'd'), element('a', 'primary_activities', 'activity', 'A', 'c'), element('b', 'primary_activities', 'activity', 'B', 'd')];
        expect(descendantCounts(elements).get('d')).toEqual({ activities: 2, clusters: 1 });
    });
});
