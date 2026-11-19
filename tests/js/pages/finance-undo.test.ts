import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { FlashUndo } from '../../../resources/js/components/list/undoFlash';

const flash: { undo: FlashUndo | null } = { undo: null };

vi.mock('@inertiajs/vue3', () => ({
    router: { get: () => {}, post: () => {}, put: () => {}, delete: () => {} },
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

describe('undo after a Yearly figures deletion', () => {
    beforeEach(() => (flash.undo = null));

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
