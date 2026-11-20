import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import InlineField from '../../../resources/js/components/fields/InlineField.vue';
import NativeSelect from '../../../resources/js/components/fields/NativeSelect.vue';
import ReportTable from '../../../resources/js/components/ui/ReportTable.vue';

const render = (component: object, props: Record<string, unknown> = {}, slots?: Record<string, (scope: never) => unknown>) =>
    renderToString(createSSRApp({ render: () => h(component, props, slots as never) }));

describe('InlineField', () => {
    it('hides the label from sight but keeps it for the input and shows the suffix', async () => {
        const html = await render(InlineField, { id: 'share', label: 'Percentage regel 1', suffix: '%', modelValue: '40,0' });

        expect(html).toContain('<label for="share" class="sr-only"');
        expect(html).toContain('w-21');
        expect(html).toContain('h-control');
        expect(html).toContain('value="40,0"');
        expect(html).toContain('>%</span>');
    });

    it('ties hint and error to the input and turns the border red', async () => {
        const html = await render(InlineField, { id: 'share', label: 'Percentage', hint: 'Samen 100%', error: 'Te veel' });

        expect(html).toContain('aria-describedby="share-hint share-error"');
        expect(html).toContain('aria-invalid="true"');
        expect(html).toContain('border-danger-edge');
    });
});

describe('NativeSelect', () => {
    const options = [{ value: 'a', label: 'Eerste' }];

    it('renders a labelled empty option first when asked and a chevron', async () => {
        const html = await render(NativeSelect, { id: 'kind', label: 'Soort', options, emptyLabel: 'Alle soorten' });

        expect(html).toMatch(/<option value=""[^>]*>Alle soorten<\/option>/);
        expect(html.indexOf('Alle soorten')).toBeLessThan(html.indexOf('Eerste'));
        expect(html).toContain('<svg');
        expect(html).toContain('border-edge');
        expect(html).toContain('h-control');
    });

    it('has no empty option without a label and ties the error to the select', async () => {
        const html = await render(NativeSelect, { id: 'kind', label: 'Soort', options, error: 'Kies een soort' });

        expect(html).not.toContain('value=""');
        expect(html).toContain('aria-describedby="kind-error"');
        expect(html).toContain('aria-invalid="true"');
    });
});

describe('ReportTable', () => {
    const columns = [{ key: 'name', label: 'Naam' }, { key: 'count', label: 'Aantal', align: 'right' as const }];

    it('has a caption, column scopes, aligned tabular figures and an optional row header', async () => {
        const html = await render(ReportTable, { caption: 'Samenvatting', columns, rows: [{ name: 'Nieuw', count: 3 }], empty: 'Geen regels', rowHeader: 'name' });

        expect(html).toContain('<caption class="sr-only">Samenvatting</caption>');
        expect(html.match(/scope="col"/g)).toHaveLength(2);
        expect(html).toContain('scope="row"');
        expect(html).toContain('tabular-nums');
        expect(html).toContain('text-right');
        expect(html).not.toContain('Geen regels');
    });

    it('shows the empty text across all columns when there are no rows and lets a slot draw a cell', async () => {
        const empty = await render(ReportTable, { caption: 'Samenvatting', columns, rows: [], empty: 'Geen regels' });
        const slotted = await render(ReportTable, { caption: 'Samenvatting', columns, rows: [{ name: 'Nieuw', count: 3 }], empty: 'x' }, { 'cell-count': (scope: { value: number }) => h('b', `${scope.value} stuks`) });

        expect(empty).toContain('colspan="2"');
        expect(empty).toContain('Geen regels');
        expect(slotted).toContain('<b>3 stuks</b>');
    });
});
