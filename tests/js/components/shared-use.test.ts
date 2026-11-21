import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it, vi } from 'vitest';
import CountsReport from '../../../resources/js/components/CountsReport.vue';
import ImportReport from '../../../resources/js/components/ImportReport.vue';
import SelectField from '../../../resources/js/components/fields/SelectField.vue';
import TextArea from '../../../resources/js/components/fields/TextArea.vue';
import TextField from '../../../resources/js/components/fields/TextField.vue';
import AllocationEditor from '../../../resources/js/pages/Finance/AllocationEditor.vue';
import CostRecordStatusList from '../../../resources/js/pages/Finance/CostRecordStatusList.vue';
import TargetTree from '../../../resources/js/pages/Finance/TargetTree.vue';
import { t } from '../../../resources/js/i18n';

vi.mock('@inertiajs/vue3', () => ({
    Head: { render: () => null },
    Link: { render: () => null },
    router: {},
    usePage: () => ({ props: {}, url: '/' }),
}));

const render = (component: object, props: Record<string, unknown>) => renderToString(createSSRApp({ render: () => h(component, props) }));

describe('one invalid edge for every field type', () => {
    it.each([
        ['TextField', TextField, {}],
        ['TextArea', TextArea, {}],
        ['SelectField', SelectField, { options: [] }],
    ])('%s draws the danger edge when it has an error', async (_name, component, extra) => {
        const html = await render(component, { id: 'f', label: 'F', error: 'Fout', modelValue: '', ...extra });
        expect(html).toContain('border-danger-edge');
        expect(html).not.toContain('border-edge ');
    });
});

describe('shared components in use', () => {
    it('renders the Toewijzen percentage through InlineField with its disabled state', async () => {
        const info = { 'e1': { id: 'e1', name: 'Domein', kind: 'domain', path: 'Domein', area: null } };
        const html = await render(AllocationEditor, { lines: [{ elementId: 'e1', basisPoints: 5000 }], info, budgetCents: 100000, disabled: true });
        expect(html).toContain('disabled:border-hairline disabled:bg-disabled');
    });

    it('renders every report table through ReportTable', async () => {
        const totals = { new: 0, updated: 0, unchanged: 0, errors: 0 };
        const imported = await render(ImportReport, { report: { lines: [{ line: 1, status: 'new', messages: [] }], totals } });
        const counted = await render(CountsReport, { prefix: 'fullx', id: 'c', keys: ['organisations'], report: { errors: [{ location: 'a', message: 'b' }], count: 1, totals: { organisations: 1 } } });
        expect(imported.match(/role="region" tabindex="0"/g)).toHaveLength(2);
        expect(counted.match(/role="region" tabindex="0"/g)).toHaveLength(2);
    });
});

describe('empty sentences', () => {
    it('says so when there are no cost records', async () => {
        const html = await render(CostRecordStatusList, { records: [], chosen: null, year: null, draft: null });
        expect(html).toContain(t('allocation_page.records_empty'));
    });

    it('says so when there are no targets', async () => {
        const html = await render(TargetTree, { targets: [], inSet: [], disabled: false, figuresAvailable: true, year: null, areaTotals: null });
        expect(html).toContain(t('allocation_page.targets_empty'));
    });

    it('says so when the Overview has no organisations', async () => {
        const { default: Overview } = await import('../../../resources/js/pages/Overview.vue');
        const html = await render(Overview, { organisations: [], layout: { areas: [], directionLabels: { mission: '', vision: '', goals: '' } }, showArchived: false, canWrite: false, templates: [], templateCards: [] });
        expect(html).toContain(t('overview.empty'));
    });
});
