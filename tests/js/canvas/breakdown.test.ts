import { nextTick, ref } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { breakdownRows, loadContributions, useContributions } from '../../../resources/js/canvas/overlay/Breakdown.vue';
import { figureFields } from '../../../resources/js/pages/Canvas/ElementFigures.vue';

const flushPromises = async (): Promise<void> => {
    for (let index = 0; index < 5; index++) await new Promise((resolve) => setTimeout(resolve, 0));
};

const contributions = [
    { id: 'a', name: 'Licentie', amountCents: 50000, basisPoints: 2500 },
    { id: 'b', name: 'Salarissen', amountCents: 123456, basisPoints: 10000 },
];

describe('breakdownRows', () => {
    it('lists the biggest Cost record first in whole euros and one decimal on the canvas', () => {
        expect(breakdownRows(contributions, false)).toEqual([
            { id: 'b', name: 'Salarissen', percentage: '100,0%', amount: '€ 1.235' },
            { id: 'a', name: 'Licentie', percentage: '25,0%', amount: '€ 500' },
        ]);
    });

    it('shows cents and two decimals in the detail panel', () => {
        expect(breakdownRows(contributions, true)[0]).toMatchObject({ percentage: '100,00%', amount: '€ 1.234,56' });
    });
});

describe('loadContributions', () => {
    it('asks for the contributions of one Element as a partial reload and asks once', async () => {
        const fetcher = vi.fn().mockResolvedValue({ json: () => Promise.resolve({ props: { contributions } }) });
        const first = await loadContributions('/canvases/1/view?overlay=financial', 'x', 'v1', fetcher as unknown as typeof fetch);
        await loadContributions('/canvases/1/view?overlay=financial', 'x', 'v1', fetcher as unknown as typeof fetch);
        expect(first).toEqual(contributions);
        expect(fetcher).toHaveBeenCalledTimes(1);
        const [url, init] = fetcher.mock.calls[0] as [string, { headers: Record<string, string> }];
        expect(url).toBe('/canvases/1/view?overlay=financial&element=x');
        expect(init.headers['X-Inertia-Partial-Data']).toBe('contributions');
        expect(init.headers['X-Inertia-Partial-Component']).toBe('Canvas/View');
    });
});

describe('useContributions', () => {
    it('refetches when the address changes, so a changed filter leaves no stale rows', async () => {
        const url = ref('/canvases/2/view?overlay=financial&cost_kind=personnel');
        const fetcher = vi.fn((target: string) => Promise.resolve({ json: () => Promise.resolve({ props: { contributions: [{ id: target, name: target.includes('project') ? 'Project' : 'Salaris', amountCents: 100, basisPoints: 100 }] } }) }));
        const rows = useContributions(() => 'e', () => url.value, () => 'v1', fetcher as unknown as typeof fetch);
        await flushPromises();
        expect(rows.value[0].name).toBe('Salaris');

        url.value = '/canvases/2/view?overlay=financial&cost_kind=project';
        await nextTick();
        expect(rows.value).toEqual([]);
        await flushPromises();
        expect(fetcher).toHaveBeenCalledTimes(2);
        expect(rows.value[0].name).toBe('Project');
    });
});

describe('figureFields', () => {
    it('shows Direct, Total, Forecast, Run, Change, Undetermined and FTE in cents and two decimals', () => {
        const money = { budget: 123456, forecast: 100000, run: 50000, change: 40000, undetermined: 33456 };
        const fields = Object.fromEntries(figureFields({ direct: { ...money, budget: 1000 }, total: money, shareBp: 3333, fteHundredths: 150 }).map((field) => [field.label, field.value]));
        expect(fields).toMatchObject({ 'Direct bedrag': '€ 10,00', 'Totaal bedrag': '€ 1.234,56', 'Aandeel van totaal': '33,33%', Prognose: '€ 1.000,00', Run: '€ 500,00', Change: '€ 400,00', Onbepaald: '€ 334,56', FTE: '1,50' });
    });
});
