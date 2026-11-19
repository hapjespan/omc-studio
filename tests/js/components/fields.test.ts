import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import ErrorSummary from '../../../resources/js/components/fields/ErrorSummary.vue';
import SelectField from '../../../resources/js/components/fields/SelectField.vue';
import TextArea from '../../../resources/js/components/fields/TextArea.vue';
import TextField from '../../../resources/js/components/fields/TextField.vue';

const render = (component: object, props: Record<string, unknown>) => renderToString(createSSRApp({ render: () => h(component, props) }));

describe('TextField', () => {
    it('ties a visible label, hint and error to the input', async () => {
        const html = await render(TextField, { id: 'budget', label: 'Budget', hint: 'Met een komma', error: 'Budget: vul een bedrag in', modelValue: '12' });

        expect(html).toContain('<label for="budget"');
        expect(html).toContain('id="budget"');
        expect(html).toContain('aria-describedby="budget-hint budget-error"');
        expect(html).toContain('aria-invalid="true"');
        expect(html).toContain('id="budget-error"');
        expect(html).toContain('Budget: vul een bedrag in');
        expect(html).toContain('value="12"');
    });

    it('marks nothing invalid without an error', async () => {
        const html = await render(TextField, { id: 'name', label: 'Naam', modelValue: '' });

        expect(html).not.toContain('aria-invalid');
        expect(html).not.toContain('aria-describedby');
    });
});

describe('TextArea', () => {
    it('keeps the entered value and ties its error', async () => {
        const html = await render(TextArea, { id: 'remark', label: 'Opmerking', error: 'Opmerking: te lang', modelValue: 'ingevuld' });

        expect(html).toContain('ingevuld</textarea>');
        expect(html).toContain('aria-describedby="remark-error"');
        expect(html).toContain('aria-invalid="true"');
    });
});

describe('SelectField', () => {
    it('labels its trigger and ties the error', async () => {
        const html = await render(SelectField, { id: 'kind', label: 'Soort', error: 'Soort: kies een soort', options: [{ value: 'a', label: 'A' }], modelValue: 'a' });

        expect(html).toContain('<label for="kind"');
        expect(html).toContain('id="kind"');
        expect(html).toContain('aria-describedby="kind-error"');
        expect(html).toContain('aria-invalid="true"');
    });
});

describe('ErrorSummary', () => {
    it('links every message to its field', async () => {
        const html = await render(ErrorSummary, { errors: { budget: 'Budget: vul een bedrag in', name: 'Naam: vul een naam in' } });

        expect(html).toContain('href="#budget"');
        expect(html).toContain('href="#name"');
    });

    it('renders nothing without errors', async () => {
        expect(await render(ErrorSummary, { errors: {} })).not.toContain('<section');
    });

    it('puts the prefix of a second form on every link', async () => {
        const html = await render(ErrorSummary, { errors: { code: 'Code: deze code klopt niet' }, prefix: 'second_factor_' });

        expect(html).toContain('href="#second_factor_code"');
    });

    it('shows a refused rule as text, because no field holds it', async () => {
        const html = await render(ErrorSummary, { errors: { rule: 'Tweede factor staat al aan' } });

        expect(html).not.toContain('href=');
        expect(html).toContain('Tweede factor staat al aan');
    });
});
