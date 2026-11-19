import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import { describe, expect, it } from 'vitest';
import AppButton from '../../../resources/js/components/ui/AppButton.vue';
import Callout from '../../../resources/js/components/ui/Callout.vue';
import Icon from '../../../resources/js/components/ui/Icon.vue';
import IconButton, { ICON_BUTTON_CLASS } from '../../../resources/js/components/ui/IconButton.vue';
import { ICON_PATHS } from '../../../resources/js/components/ui/icons';
import LogoMark from '../../../resources/js/components/ui/LogoMark.vue';
import Pill from '../../../resources/js/components/ui/Pill.vue';
import SearchField from '../../../resources/js/components/ui/SearchField.vue';

const render = (component: object, props: Record<string, unknown> = {}, slot = '') =>
    renderToString(createSSRApp({ render: () => h(component, props, slot === '' ? undefined : { default: () => slot }) }));

describe('AppButton', () => {
    it('fills the primary button with ink at 36 pixels high', async () => {
        const html = await render(AppButton, { variant: 'primary' }, 'Opslaan');

        expect(html).toContain('bg-ink');
        expect(html).toContain('min-h-control');
        expect(html).toContain('Opslaan');
    });

    it('draws the secondary button white with a border and the danger button in the danger colours', async () => {
        expect(await render(AppButton, {}, 'Annuleren')).toContain('border-edge');
        const danger = await render(AppButton, { variant: 'danger' }, 'Verwijderen');
        expect(danger).toContain('text-danger');
        expect(danger).toContain('border-danger-edge');
    });

    it('offers the smaller sizes of the contract', async () => {
        expect(await render(AppButton, { size: 'dense' }, 'Opslaan')).toContain('min-h-dense');
        expect(await render(AppButton, { size: 'small' }, 'Opslaan')).toContain('min-h-mark');
    });

    it('submits only when asked to', async () => {
        expect(await render(AppButton, {}, 'Opslaan')).toContain('type="button"');
        expect(await render(AppButton, { type: 'submit' }, 'Opslaan')).toContain('type="submit"');
    });
});

describe('IconButton', () => {
    it('keeps its accessible name and measures 28 by 28', async () => {
        const html = await render(IconButton, { label: 'Selectie omhoog', icon: 'chevron-up' });

        expect(html).toContain('aria-label="Selectie omhoog"');
        expect(html).toContain('h-mark');
        expect(html).toContain('w-mark');
        expect(html).toContain('<svg');
    });

    it('passes its disabled state on', async () => {
        expect(await render(IconButton, { label: 'Selectie omhoog', icon: 'chevron-up', disabled: true })).toContain('disabled');
    });
});

describe('Icon', () => {
    it('renders an inline decorative svg', async () => {
        const html = await render(Icon, { name: 'search' });

        expect(html).toContain('aria-hidden="true"');
        expect(html).toContain('focusable="false"');
        expect(html).toContain('currentColor');
    });
});

describe('LogoMark', () => {
    it('draws the grid in a rounded ink square without an outside asset', async () => {
        const html = await render(LogoMark);

        expect(html).toContain('<svg');
        expect(html).not.toContain('<img');
        expect(html).toContain('fill-ink');
    });
});

describe('Pill', () => {
    it('is round and carries its text', async () => {
        expect(await render(Pill, {}, 'Voorbeelddata')).toContain('rounded-full');
        expect(await render(Pill, { tone: 'warning' }, 'leeg')).toContain('text-warning');
    });
});

describe('Callout', () => {
    it('shows a warning with its icon and bold kind', async () => {
        const html = await render(Callout, { tone: 'warning', label: 'Leeg cluster:' }, 'Veiligheid');

        expect(html).toContain('bg-warning-soft');
        expect(html).toContain('<svg');
        expect(html).toContain('Leeg cluster:');
        expect(html).toContain('Veiligheid');
    });

    it('shows a success callout in the success colours', async () => {
        expect(await render(Callout, { tone: 'success' }, 'Totalen sluiten')).toContain('bg-success-soft');
    });
});

describe('SearchField', () => {
    it('ties a label to the field and shows the search icon', async () => {
        const html = await render(SearchField, { id: 'area-search', label: 'Zoek in deze area', placeholder: 'Zoek in deze area', modelValue: '' });

        expect(html).toContain('<label for="area-search"');
        expect(html).toContain('id="area-search"');
        expect(html).toContain('type="search"');
        expect(html).toContain('<svg');
    });
});

describe('the reorder arrows of the tree', () => {
    const source = readFileSync(fileURLToPath(new URL('../../../resources/js/pages/Canvas/ReorderButtons.vue', import.meta.url)), 'utf8');

    it('keeps the look of the icon button without an instance per row', () => {
        expect(source).toContain('ICON_BUTTON_CLASS');
        expect(source).not.toContain('<IconButton');
        expect(ICON_BUTTON_CLASS).toContain('h-mark');
    });

    it('inlines the same chevron paths as the icon component', () => {
        expect(source).toContain(`d="${ICON_PATHS['chevron-up']}"`);
        expect(source).toContain(`d="${ICON_PATHS['chevron-down']}"`);
    });
});
