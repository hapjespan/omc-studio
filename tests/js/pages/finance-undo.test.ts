import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { FlashUndo } from '../../../resources/js/components/list/undoFlash';

type FlashBulk = { done: number; skipped: { name: string; reason: string }[] };

const flash: { undo: FlashUndo | null; bulk: FlashBulk | null } = { undo: null, bulk: null };

vi.mock('@inertiajs/vue3', () => ({
    router: { get: () => {}, post: () => {}, put: () => {}, delete: () => {}, on: () => () => {} },
    usePage: () => ({ props: { errors: {}, flash } }),
    Head: { render: () => null },
}));

const YearlyFiguresForm = (await import('../../../resources/js/pages/Finance/YearlyFiguresForm.vue')).default;
const CostRecords = (await import('../../../resources/js/pages/Finance/CostRecords.vue')).default;

const row = { id: 'c1', name: 'Licentie', has_figures: true, year_set_count: 2, budget: '€ 1,00', forecast: '', allocation: 'allocated', figures: { budget: '1,00', forecast: '', run: '', change: '', fte: '', reference_date: '', remark: '' } };
const query = { search: '', sort: null, direction: 'asc' as const, filters: {}, archived: false };

const panel = () => renderToString(createSSRApp({ render: () => h(YearlyFiguresForm, { row, year: 2026 }) }));
const list = () => renderToString(createSSRApp({
    render: () => h(CostRecords, { rows: [row], count: 1, query, organisation: { id: 1, name: 'Organisatie', archived: false }, year: 2026, years: [{ id: 1, year: 2026 }], owners: [], applications: [], costKinds: ['other'] }),
}));

describe('result of a bulk deletion', () => {
    beforeEach(() => {
        flash.undo = null;
        flash.bulk = null;
    });

    it('counts the deleted rows and names every skipped row with its reason', async () => {
        flash.bulk = { done: 2, skipped: [{ name: 'Drie', reason: '2026 is definitief in canvas Strategie. Heropen het jaar eerst, of archiveer de kostenpost' }] };
        flash.undo = { token: 't3', message: '2 kostenposten verwijderd' };
        const html = await list();

        expect(html).toContain('2 verwijderd, 1 overgeslagen');
        expect(html).toContain('Drie: 2026 is definitief in canvas Strategie');
        expect(html).toContain('2 kostenposten verwijderd');
        expect(html).toContain('Ongedaan maken');
    });

    it('reports nothing without a bulk action', async () => {
        expect(await list()).not.toContain('overgeslagen');
    });
});

describe('undo after a Yearly figures deletion', () => {
    beforeEach(() => {
        flash.undo = null;
        flash.bulk = null;
    });

    it('offers Ongedaan maken in the panel', async () => {
        flash.undo = { token: 't1', message: 'Jaarwaarden verwijderd', scope: 'figures' };
        const html = await panel();

        expect(html).toContain('Jaarwaarden verwijderd');
        expect(html).toContain('Ongedaan maken');
    });

    it('offers nothing in the panel without a deletion', async () => {
        expect(await panel()).not.toContain('Ongedaan maken');
    });

    it('leaves a Cost record deletion to the list', async () => {
        flash.undo = { token: 't2', message: 'Kostenpost verwijderd', scope: undefined };

        expect(await panel()).not.toContain('Ongedaan maken');
        expect(await list()).toContain('Kostenpost verwijderd');
    });

    it('keeps the Yearly figures message out of the list', async () => {
        flash.undo = { token: 't1', message: 'Jaarwaarden verwijderd', scope: 'figures' };

        expect(await list()).not.toContain('Ongedaan maken');
    });
});
