import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import FinancialCard, { cardLines, fitParts } from '../../../resources/js/canvas/overlay/FinancialCard.vue';
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
        expect(lines.share).toBe('12,4% van totaal');
        expect(lines.forecast).toBe('Prognose € 1.100 · 89,1% van budget');
        expect(lines.counts).toBe('Activiteiten 3 · Clusters 1');
        expect(lines.empty).toBeNull();
    });

    it('says that no costs are allocated for the year', () => {
        expect(cardLines(figures(0, 0, null), { activities: 0, clusters: 0 }, 2026).empty).toBe('Geen kosten toegewezen in 2026');
    });
});

describe('fitParts', () => {
    it('drops counts, then the forecast line, then the bar, then the Share as the card gets smaller', () => {
        expect([...fitParts(200)]).toEqual(['counts', 'forecast', 'bar', 'share']);
        expect([...fitParts(76)].sort()).toEqual(['bar', 'forecast', 'share']);
        expect([...fitParts(60)].sort()).toEqual(['bar', 'share']);
        expect([...fitParts(40)].sort()).toEqual(['share']);
        expect([...fitParts(30)]).toEqual([]);
    });
});

describe('FinancialCard', () => {
    it('draws the bar with the Forecast fill and the Budget stripe', async () => {
        const drawn = computeCanvasLayout({ ...emptyInput(), elements: [element('d', 'primary_activities', 'domain', 'Onderwijs')] }, fakeMeasurer);
        const block = { ...drawn.blocks.find((candidate) => candidate.id === 'd')!, drawnHeight: 200 };
        const html = await renderToString(createSSRApp({ render: () => h(FinancialCard, { block, figures: figures(1000, 500, 1000), counts: { activities: 0, clusters: 0 }, year: 2026 }) }));
        expect(html).toContain('data-bar-fill');
        expect(html).toContain('width:50%');
        expect(html).toContain('left:100%');
        expect(html).toContain('Totaal € 10');
    });
});

describe('descendantCounts', () => {
    it('counts the Activities and Clusters under every Domain', () => {
        const elements = [element('d', 'primary_activities', 'domain', 'D'), element('c', 'primary_activities', 'cluster', 'C', 'd'), element('a', 'primary_activities', 'activity', 'A', 'c'), element('b', 'primary_activities', 'activity', 'B', 'd')];
        expect(descendantCounts(elements).get('d')).toEqual({ activities: 2, clusters: 1 });
    });
});
