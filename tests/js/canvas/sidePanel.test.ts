import { describe, expect, it } from 'vitest';
import type { CanvasFigures, Money } from '../../../resources/js/canvas/overlay/figures';
import { closingLine, panelGroups } from '../../../resources/js/pages/Canvas/FigureSidePanel.vue';

const money = (budget: number, forecast = budget): Money => ({ budget, forecast, run: 0, change: 0, undetermined: 0 });
const figures: CanvasFigures = {
    elements: {},
    areas: {},
    canvasTotal: money(150050, 120000),
    sidePanel: { allocated: { budget: 100000, forecast: 90000, count: 1 }, unknown: { budget: 50050, forecast: 30000, count: 2 } },
    breakdowns: { costKind: { personnel: money(100000), project: money(50050) }, modality: { common: money(40000), none: money(60000), unknown_allocation: money(50050) }, split: { run: 70000, change: 50000, undetermined: 30050 } },
};
const values = (key: string): Record<string, string> => Object.fromEntries(panelGroups(figures).find((group) => group.key === key)!.lines.map((line) => [line.label, line.value]));

describe('side panel', () => {
    it('shows Budget, Forecast against Budget, Toegewezen and Onbekende allocatie in whole euros', () => {
        expect(values('totals')).toEqual({
            Budget: '€ 1.501',
            Prognose: '€ 1.200 (80,0% van budget)',
            Toegewezen: '1 kostenpost · € 1.000',
            'Onbekende allocatie': '2 kostenposten · € 501',
        });
    });

    it('proves the figures close to the cent', () => {
        expect(closingLine(figures)).toBe('Totalen sluiten: € 1.000,00 + € 500,50 = € 1.500,50');
    });

    it('rounds every amount on its own, so rounded parts need not add up', () => {
        expect(values('totals').Budget).toBe('€ 1.501');
        expect(values('totals').Toegewezen).toContain('€ 1.000');
    });

    it('lists every Cost kind, every Modality with Geen modaliteit and Onbekende allocatie, and Run, Change and Onbepaald', () => {
        expect(values('cost_kind')).toMatchObject({ Personeel: '€ 1.000', Project: '€ 501', Overig: '€ 0' });
        expect(Object.keys(values('modality'))).toEqual(['Common', 'Adaptive', 'Specialized', 'Distinct', 'Geen modaliteit', 'Onbekende allocatie']);
        expect(values('split')).toEqual({ Run: '€ 700', Change: '€ 500', Onbepaald: '€ 301' });
    });
});
